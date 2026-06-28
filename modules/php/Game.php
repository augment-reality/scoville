<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew;

require_once __DIR__ . '/material.inc.php';

use Bga\Games\Scovillenew\States\AuctionBid;
use Bga\Games\Scovillenew\States\AuctionClaim;
use Bga\Games\Scovillenew\States\AuctionResolve;
use Bga\Games\Scovillenew\States\EndScore;
use Bga\Games\Scovillenew\States\Fulfillment;
use Bga\Games\Scovillenew\States\Harvesting;
use Bga\Games\Scovillenew\States\Planting;
use Bga\Games\Scovillenew\States\TimeCheck;
use Bga\Games\Scovillenew\States\TurnOrderChoice;

class Game extends \Bga\GameFramework\Table
{
    // -------------------------------------------------------------------------
    // Deck components (created in constructor)
    // -------------------------------------------------------------------------

    public \Bga\GameFramework\Components\Deck $auctionCards;
    public \Bga\GameFramework\Components\Deck $marketCards;
    public \Bga\GameFramework\Components\Deck $recipeCards;

    public function __construct()
    {
        parent::__construct();

        $this->auctionCards = $this->deckFactory->createDeck('auction_card');
        $this->marketCards  = $this->deckFactory->createDeck('market_card');
        $this->recipeCards  = $this->deckFactory->createDeck('recipe_card');

        // Notification decorator: fill player_name automatically
        $this->bga->notify->addDecorator(function (string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name'])
                && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
            return $args;
        });
    }

    // -------------------------------------------------------------------------
    // Game progression
    // -------------------------------------------------------------------------

    public function getGameProgression(): int
    {
        // Rough estimate: progress through recipe cards consumed
        $numPlayers = count($this->loadPlayersBasicInfos());
        $total = Material::DISPLAY_COUNTS[$numPlayers]['recipe'] * 2; // morning+afternoon combined
        $remaining = (int) static::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM `recipe_card` WHERE `card_location` = 'display'"
        );
        if ($total <= 0) return 100;
        $consumed = $total - $remaining;
        return max(0, min(100, (int) round(($consumed / $total) * 100)));
    }

    // -------------------------------------------------------------------------
    // setupNewGame
    // -------------------------------------------------------------------------

    protected function setupNewGame($players, $options = []): string
    {
        $numPlayers = count($players);

        // --- Players ---
        $gameinfos     = $this->getGameinfos();
        $defaultColors = $gameinfos['player_colors'];

        $queryValues = [];
        $turnOrder   = 1;
        foreach ($players as $playerId => $player) {
            $color = array_shift($defaultColors);
            $queryValues[] = vsprintf("(%s, '%s', '%s', %d)", [
                $playerId,
                $color,
                addslashes($player['player_name']),
                $turnOrder++,
            ]);
        }
        static::DbQuery(sprintf(
            "INSERT INTO `player` (`player_id`, `player_color`, `player_name`, `player_turn_order`)
             VALUES %s",
            implode(',', $queryValues)
        ));
        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        $playerIds = array_keys($players);

        // --- Pepper inventory: all players start with 1 red + 1 yellow + 1 blue ---
        // (2 of the 3 will be planted below; but per rules each player picks 2 to
        // plant and returns the 3rd — implemented as: give all 3, plant 2, return 1.
        // For simplicity we give each player all 3 up front; the Starting Plots phase
        // will deduct 2 when planting.)
        foreach ($playerIds as $pid) {
            foreach (['red', 'yellow', 'blue'] as $color) {
                static::DbQuery(
                    "INSERT INTO `player_pepper` (`player_id`, `pepper_color`, `count`) VALUES ($pid, '$color', 1)"
                );
            }
        }

        // --- Bonus tiles: 1 of each type per player ---
        foreach ($playerIds as $pid) {
            foreach (array_keys(Material::BONUS_TILES) as $type) {
                static::DbQuery(
                    "INSERT INTO `bonus_tile` (`player_id`, `tile_type`) VALUES ($pid, '$type')"
                );
            }
        }

        // --- Auction cards ---
        $auctionMorning = [];
        $auctionAfternoon = [];
        foreach (Material::AUCTION_CARD_DEFS as $idx => $def) {
            $deck = $def['deck'] === 'morning' ? 'morning' : 'afternoon';
            $card = ['type' => $def['deck'], 'type_arg' => $idx];
            if ($deck === 'morning') {
                $auctionMorning[] = $card;
            } else {
                $auctionAfternoon[] = $card;
            }
        }
        $this->auctionCards->createCards($auctionMorning, 'deck_morning');
        $this->auctionCards->createCards($auctionAfternoon, 'deck_afternoon');
        $this->auctionCards->shuffle('deck_morning');
        $this->auctionCards->shuffle('deck_afternoon');

        // Draw initial display (N = numPlayers slots)
        $slots = Material::auctionSlots($numPlayers);
        $this->auctionCards->pickCardsForLocation($slots, 'deck_morning', 'display');

        // --- Market cards ---
        $marketMorning = [];
        $marketAfternoon = [];
        foreach (Material::MARKET_CARD_DEFS as $idx => $def) {
            $card = ['type' => $def['deck'], 'type_arg' => $idx];
            if ($def['deck'] === 'morning') {
                $marketMorning[] = $card;
            } else {
                $marketAfternoon[] = $card;
            }
        }
        $this->marketCards->createCards($marketMorning, 'deck_morning');
        $this->marketCards->createCards($marketAfternoon, 'deck_afternoon');
        $this->marketCards->shuffle('deck_morning');
        $this->marketCards->shuffle('deck_afternoon');

        $displayCount = Material::DISPLAY_COUNTS[$numPlayers]['market'];
        $this->marketCards->pickCardsForLocation($displayCount, 'deck_morning', 'display');
        // Discard the remainder of the morning market deck (per rules, not needed until afternoon)
        // Actually: rules say return remainder to box; we just leave them in deck_morning but won't use them.

        // --- Recipe cards ---
        $recipeDefs = [];
        foreach (Material::RECIPE_CARD_DEFS as $idx => $def) {
            $recipeDefs[] = ['type' => 'recipe', 'type_arg' => $idx];
        }
        $this->recipeCards->createCards($recipeDefs, 'deck');
        $this->recipeCards->shuffle('deck');

        $recipeCount = Material::DISPLAY_COUNTS[$numPlayers]['recipe'];
        $this->recipeCards->pickCardsForLocation($recipeCount, 'deck', 'display');
        // Discard the remainder (per rules, return to box; leave in 'deck' but unused)

        // --- Award plaques ---
        foreach (Material::AWARD_PLAQUES as $plaque) {
            static::DbQuery(sprintf(
                "INSERT INTO `award_plaque` (`pepper_color`, `points`) VALUES ('%s', %d)",
                $plaque['color'],
                $plaque['points']
            ));
        }
        // For 2p/3p: remove the highest-value plaque from each stack
        if ($numPlayers <= 3) {
            foreach (['red', 'yellow', 'blue', 'striped', 'white', 'black'] as $color) {
                $maxId = (int) static::getUniqueValueFromDB(
                    "SELECT `plaque_id` FROM `award_plaque`
                     WHERE `pepper_color` = '$color' AND `player_id` IS NULL
                     ORDER BY `points` DESC LIMIT 1"
                );
                if ($maxId) {
                    static::DbQuery("DELETE FROM `award_plaque` WHERE `plaque_id` = $maxId");
                }
            }
        }

        // --- Starting plots ---
        // Per rules each player secretly takes R/Y/B, picks 2 to plant.
        // For BGA we automate: randomly plant 2 of the 3 starting colours per player,
        // return the third to their supply.
        // Actually the rules say: "Randomly choose two of those [3 peppers] to place
        // on the Starting Plots" — two SPECIFIC plots on the board, and all players
        // do this simultaneously.  All players are planting to the SAME two starting plots,
        // so we only do this once (the board has exactly 2 starting plots at setup).
        // Players are NOT choosing which starting plot to put which colour on.
        //
        // Simplified implementation: place one random primary on each starting plot.
        $primaries = ['red', 'yellow', 'blue'];
        shuffle($primaries);
        foreach (Material::STARTING_PLOTS as $plot) {
            $color = array_shift($primaries);
            static::DbQuery(sprintf(
                "INSERT INTO `pepper_field` (`plot_row`, `plot_col`, `pepper_color`) VALUES (%d, %d, '%s')",
                $plot['row'], $plot['col'], $color
            ));
        }

        // --- Global variables ---
        $this->globals->set('round_number', 1);
        $this->globals->set('is_afternoon', 0);
        $this->globals->set('final_round_mode', 0); // 0=normal, 1=one-more-round, 2=end
        $this->globals->set('phase_player_idx', 0);
        $this->globals->set('bid_player_idx', 0);
        $this->globals->set('bid_order', []); // player IDs in descending bid order
        $this->globals->set('harvest_steps', 0);
        $this->globals->set('harvest_path', []); // notch IDs visited this harvest turn

        // Round 1 skips the bid phase — go directly to AuctionClaim
        return AuctionClaim::class;
    }

    // -------------------------------------------------------------------------
    // getAllDatas — visible to the current player
    // -------------------------------------------------------------------------

    protected function getAllDatas(int $currentPlayerId): array
    {
        $result = [];

        // Players
        $result['players'] = $this->getCollectionFromDb(
            "SELECT `player_id` AS `id`, `player_score` AS `score`,
                    `player_score_aux` AS `score_aux`,
                    `player_color` AS `color`,
                    `player_coins` AS `coins`,
                    `player_turn_order` AS `turn_order`,
                    `farmer_notch`
             FROM `player`"
        );

        // Each player's pepper counts (hidden behind screen — return only current player's)
        $result['my_peppers'] = $this->getCollectionFromDb(
            "SELECT `pepper_color` AS `color`, `count`
             FROM `player_pepper`
             WHERE `player_id` = $currentPlayerId"
        );

        // Other players' pepper counts are hidden; reveal only totals (optional)
        $result['pepper_counts'] = $this->getCollectionFromDb(
            "SELECT `player_id`, SUM(`count`) AS `total`
             FROM `player_pepper` GROUP BY `player_id`"
        );

        // Planted peppers on the field (public)
        $result['pepper_field'] = $this->getCollectionFromDb(
            "SELECT CONCAT(`plot_row`, '_', `plot_col`) AS `key`,
                    `plot_row` AS `row`, `plot_col` AS `col`, `pepper_color` AS `color`
             FROM `pepper_field`"
        );

        // Auction display
        $result['auction_display'] = array_values(
            $this->auctionCards->getCardsInLocation('display')
        );

        // Market display
        $result['market_display'] = array_values(
            $this->marketCards->getCardsInLocation('display')
        );

        // Recipe display
        $result['recipe_display'] = array_values(
            $this->recipeCards->getCardsInLocation('display')
        );

        // Current player's fulfilled market/recipe cards (for scoring display)
        $result['my_market_cards'] = array_values(
            $this->marketCards->getCardsInLocation('player', $currentPlayerId)
        );
        $result['my_recipe_cards'] = array_values(
            $this->recipeCards->getCardsInLocation('player', $currentPlayerId)
        );

        // Award plaques still on City Hall
        $result['city_hall_plaques'] = $this->getCollectionFromDb(
            "SELECT `plaque_id`, `pepper_color`, `points`
             FROM `award_plaque` WHERE `player_id` IS NULL
             ORDER BY `pepper_color`, `points` DESC"
        );

        // Current player's earned plaques
        $result['my_plaques'] = $this->getCollectionFromDb(
            "SELECT `plaque_id`, `pepper_color`, `points`
             FROM `award_plaque` WHERE `player_id` = $currentPlayerId"
        );

        // Current player's bonus tiles
        $result['my_bonus_tiles'] = $this->getCollectionFromDb(
            "SELECT `tile_id`, `tile_type`, `used`
             FROM `bonus_tile` WHERE `player_id` = $currentPlayerId"
        );

        // Globals the client needs
        $result['is_afternoon']  = $this->globals->get('is_afternoon');
        $result['round_number']  = $this->globals->get('round_number');
        $result['final_round']   = $this->globals->get('final_round_mode');

        return $result;
    }

    // -------------------------------------------------------------------------
    // Shared helpers used by state classes
    // -------------------------------------------------------------------------

    /** All player IDs sorted by turn_order ASC */
    public function getPlayerIdsInTurnOrder(): array
    {
        return array_column(
            $this->getCollectionFromDb(
                "SELECT `player_id` FROM `player` ORDER BY `player_turn_order` ASC"
            ),
            'player_id'
        );
    }

    /** All player IDs sorted by turn_order DESC (reverse, for Harvesting) */
    public function getPlayerIdsInReverseOrder(): array
    {
        return array_column(
            $this->getCollectionFromDb(
                "SELECT `player_id` FROM `player` ORDER BY `player_turn_order` DESC"
            ),
            'player_id'
        );
    }

    /**
     * Return the next active player for a phase that cycles forward through
     * turn order.  Increments phase_player_idx.
     * Returns null if all players have acted.
     */
    public function advancePhasePlayer(array $orderedIds): ?int
    {
        $idx = (int) $this->globals->get('phase_player_idx');
        $idx++;
        $this->globals->set('phase_player_idx', $idx);
        return $orderedIds[$idx] ?? null;
    }

    /** Reset phase player index to 0 and activate the first player in the list */
    public function startPhaseWithFirstPlayer(array $orderedIds): void
    {
        $this->globals->set('phase_player_idx', 0);
        $this->gamestate->changeActivePlayer($orderedIds[0]);
    }

    /** Give pepper(s) to a player from the supply */
    public function givePeppers(int $playerId, array $peppers): void
    {
        foreach ($peppers as $color) {
            static::DbQuery(
                "INSERT INTO `player_pepper` (`player_id`, `pepper_color`, `count`) VALUES ($playerId, '$color', 1)
                 ON DUPLICATE KEY UPDATE `count` = `count` + 1"
            );
        }
    }

    /** Return peppers to supply (deduct from player) */
    public function takePeppers(int $playerId, array $cost): void
    {
        foreach ($cost as $color => $count) {
            static::DbQuery(
                "UPDATE `player_pepper` SET `count` = `count` - $count
                 WHERE `player_id` = $playerId AND `pepper_color` = '$color'"
            );
        }
    }

    /** Check if player has enough peppers for a given cost */
    public function playerCanAfford(int $playerId, array $cost): bool
    {
        foreach ($cost as $color => $needed) {
            $have = (int) static::getUniqueValueFromDB(
                "SELECT `count` FROM `player_pepper`
                 WHERE `player_id` = $playerId AND `pepper_color` = '$color'"
            );
            if ($have < $needed) return false;
        }
        return true;
    }

    /** Modify a player's coin count; returns new total */
    public function addCoins(int $playerId, int $delta): int
    {
        static::DbQuery(
            "UPDATE `player` SET `player_coins` = GREATEST(0, `player_coins` + $delta)
             WHERE `player_id` = $playerId"
        );
        return (int) static::getUniqueValueFromDB(
            "SELECT `player_coins` FROM `player` WHERE `player_id` = $playerId"
        );
    }

    /**
     * Award a plaque (if any remain) for a given planted pepper colour.
     * Returns the awarded plaque row, or null if none available.
     */
    public function awardPlaque(int $playerId, string $plantedColor): ?array
    {
        $category = Material::plaqueCategoryForPepper($plantedColor);
        if ($category === null) return null;

        $plaque = static::getObjectFromDB(
            "SELECT * FROM `award_plaque`
             WHERE `pepper_color` = '$category' AND `player_id` IS NULL
             ORDER BY `points` DESC LIMIT 1"
        );
        if (!$plaque) return null;

        $id = (int) $plaque['plaque_id'];
        static::DbQuery(
            "UPDATE `award_plaque` SET `player_id` = $playerId WHERE `plaque_id` = $id"
        );
        return $plaque;
    }

    /**
     * Compute the sell value of one pepper of a given colour.
     * Price = floor(plotsOfColor / 2) dollars each.
     */
    public function getPepperSellPrice(string $color): int
    {
        $count = (int) static::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM `pepper_field` WHERE `pepper_color` = '$color'"
        );
        return (int) floor($count / 2);
    }

    /**
     * Refill the auction display from the current deck.
     */
    public function refillAuctionDisplay(int $numPlayers): void
    {
        $isAfternoon = (bool) $this->globals->get('is_afternoon');
        $deckLoc = $isAfternoon ? 'deck_afternoon' : 'deck_morning';
        $inDisplay = count($this->auctionCards->getCardsInLocation('display'));
        $needed = Material::auctionSlots($numPlayers) - $inDisplay;
        if ($needed > 0) {
            $remaining = $this->auctionCards->countCardInLocation($deckLoc);
            if ($remaining === 0) {
                // Reshuffle discards into the current deck
                $this->auctionCards->shuffle('discard');
                static::DbQuery(
                    "UPDATE `auction_card` SET `card_location` = '$deckLoc'
                     WHERE `card_location` = 'discard'"
                );
            }
            $this->auctionCards->pickCardsForLocation(min($needed, $remaining), $deckLoc, 'display');
        }
    }

    // -------------------------------------------------------------------------
    // upgradeTableDb
    // -------------------------------------------------------------------------

    public function upgradeTableDb($from_version): void {}

    // -------------------------------------------------------------------------
    // Debug helpers
    // -------------------------------------------------------------------------

    public function debug_goToState(int $state = 10): void
    {
        $this->gamestate->jumpToState($state);
    }
}