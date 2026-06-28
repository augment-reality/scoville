<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Scovillenew\Game;
use Bga\Games\Scovillenew\Material;

/**
 * FULFILLMENT phase — each player (in turn order) may perform any or all of:
 *   1. Fulfill one Market card
 *   2. Complete one Recipe at the Chili Cookoff
 *   3. Sell up to 5 peppers of one colour
 * Then they must call actEndFulfillment() to pass.
 *
 * Each action may be done at most once per round per player.
 */
class Fulfillment extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 40,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function onEnteringState(int $activePlayerId): void
    {
        $idx = (int) $this->game->globals->get('phase_player_idx');
        if ($idx === 0) {
            $ordered = $this->game->getPlayerIdsInTurnOrder();
            $this->game->gamestate->changeActivePlayer($ordered[0]);
        }
        $this->game->globals->set('fulfillment_done_market', 0);
        $this->game->globals->set('fulfillment_done_recipe', 0);
        $this->game->globals->set('fulfillment_done_sell', 0);
    }

    public function getArgs(): array
    {
        return [
            'can_market' => !(bool) $this->game->globals->get('fulfillment_done_market'),
            'can_recipe' => !(bool) $this->game->globals->get('fulfillment_done_recipe'),
            'can_sell'   => !(bool) $this->game->globals->get('fulfillment_done_sell'),
            'market_display' => array_values(
                $this->game->marketCards->getCardsInLocation('display')
            ),
            'recipe_display' => array_values(
                $this->game->recipeCards->getCardsInLocation('display')
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // Visit the Farmers' Market
    // -------------------------------------------------------------------------

    #[PossibleAction]
    public function actFulfillMarket(int $card_id, int $activePlayerId, array $args): string
    {
        if (!$args['can_market']) {
            throw new UserException("You have already visited the Farmers' Market this round");
        }

        $card = $this->game->marketCards->getCard($card_id);
        if (!$card || $card['location'] !== 'display') {
            throw new UserException("That Market card is not available");
        }

        $defIdx = (int) $card['type_arg'];
        $def    = Material::MARKET_CARD_DEFS[$defIdx];

        if (!$this->game->playerCanAfford($activePlayerId, $def['cost'])) {
            throw new UserException("You cannot afford this card");
        }

        // Pay the cost
        $this->game->takePeppers($activePlayerId, $def['cost']);

        // Receive rewards
        $this->game->addCoins($activePlayerId, $def['coins']);
        $pepperList = [];
        foreach ($def['peppers'] as $color => $count) {
            for ($i = 0; $i < $count; $i++) $pepperList[] = $color;
        }
        if (!empty($pepperList)) $this->game->givePeppers($activePlayerId, $pepperList);

        // Move card to player's collection
        $this->game->marketCards->moveCard($card_id, 'player', $activePlayerId);

        // Update score (point value on the card itself)
        $this->bga->playerScore->inc($activePlayerId, $def['points']);

        $this->bga->notify->all('marketFulfilled',
            clienttranslate('${player_name} fulfills a Market card'), [
            'player_id'      => $activePlayerId,
            'card_id'        => $card_id,
            'coins_gained'   => $def['coins'],
            'peppers_gained' => $pepperList,
            'points'         => $def['points'],
        ]);

        $this->game->globals->set('fulfillment_done_market', 1);
        return Fulfillment::class;
    }

    // -------------------------------------------------------------------------
    // Chili Cookoff
    // -------------------------------------------------------------------------

    #[PossibleAction]
    public function actFulfillRecipe(int $card_id, int $activePlayerId, array $args): string
    {
        if (!$args['can_recipe']) {
            throw new UserException("You have already competed at the Chili Cookoff this round");
        }

        $card = $this->game->recipeCards->getCard($card_id);
        if (!$card || $card['location'] !== 'display') {
            throw new UserException("That Recipe card is not available");
        }

        $defIdx = (int) $card['type_arg'];
        $def    = Material::RECIPE_CARD_DEFS[$defIdx];

        if (!$this->game->playerCanAfford($activePlayerId, $def['cost'])) {
            throw new UserException("You cannot afford this recipe");
        }

        $this->game->takePeppers($activePlayerId, $def['cost']);
        $this->game->recipeCards->moveCard($card_id, 'player', $activePlayerId);
        $this->bga->playerScore->inc($activePlayerId, $def['points']);

        $this->bga->notify->all('recipeFulfilled',
            clienttranslate('${player_name} completes a recipe for ${points} points!'), [
            'player_id' => $activePlayerId,
            'card_id'   => $card_id,
            'points'    => $def['points'],
        ]);

        $this->game->globals->set('fulfillment_done_recipe', 1);
        return Fulfillment::class;
    }

    // -------------------------------------------------------------------------
    // Sell a batch of peppers
    // -------------------------------------------------------------------------

    #[PossibleAction]
    public function actSellPeppers(string $color, int $count, int $activePlayerId, array $args): string
    {
        if (!$args['can_sell']) {
            throw new UserException("You have already sold peppers this round");
        }
        if (!in_array($color, Material::PEPPERS, true)) {
            throw new UserException("Invalid pepper colour");
        }
        if ($count < 1 || $count > Material::MAX_SELL_PEPPERS) {
            throw new UserException("You may sell 1–" . Material::MAX_SELL_PEPPERS . " peppers");
        }

        $have = (int) static::getUniqueValueFromDB(
            "SELECT `count` FROM `player_pepper`
             WHERE `player_id` = $activePlayerId AND `pepper_color` = '$color'"
        );
        if ($have < $count) {
            throw new UserException("You do not have $count $color peppers");
        }

        $priceEach = $this->game->getPepperSellPrice($color);
        $earned    = $priceEach * $count;

        $this->game->takePeppers($activePlayerId, [$color => $count]);
        $this->game->addCoins($activePlayerId, $earned);

        $this->bga->notify->all('peppersSold',
            clienttranslate('${player_name} sells ${count} ${color} pepper(s) for $${earned}'), [
            'player_id' => $activePlayerId,
            'color'     => $color,
            'count'     => $count,
            'earned'    => $earned,
            'i18n'      => ['color'],
        ]);

        $this->game->globals->set('fulfillment_done_sell', 1);
        return Fulfillment::class;
    }

    // -------------------------------------------------------------------------
    // End fulfillment turn
    // -------------------------------------------------------------------------

    #[PossibleAction]
    public function actEndFulfillment(int $activePlayerId): string
    {
        $this->bga->notify->all('fulfillmentPassed',
            clienttranslate('${player_name} passes'), [
            'player_id' => $activePlayerId,
        ]);

        return $this->advanceOrNext($activePlayerId);
    }

    // -------------------------------------------------------------------------

    private function advanceOrNext(int $activePlayerId): string
    {
        $ordered = $this->game->getPlayerIdsInTurnOrder();
        $next    = $this->game->advancePhasePlayer($ordered);

        if ($next !== null) {
            $this->game->globals->set('fulfillment_done_market', 0);
            $this->game->globals->set('fulfillment_done_recipe', 0);
            $this->game->globals->set('fulfillment_done_sell', 0);
            $this->game->gamestate->changeActivePlayer($next);
            return Fulfillment::class;
        }

        $this->game->globals->set('phase_player_idx', 0);
        return TimeCheck::class;
    }

    public function zombie(int $playerId): string
    {
        return $this->actEndFulfillment($playerId);
    }
}
