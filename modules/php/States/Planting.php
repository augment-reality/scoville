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
 * PLANTING phase — each player (in turn order) must plant one pepper
 * from their supply onto the field. May optionally use the "extra_plant"
 * bonus tile to plant a second pepper.
 *
 * A plot is a valid planting target if it is empty AND horizontally or
 * vertically adjacent to at least one already-planted plot.
 */
class Planting extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 20,
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
        // Reset the "has planted once this phase turn" flag
        $this->game->globals->set('planted_this_turn', 0);
        $this->game->globals->set('plaque_earned_this_turn', 0);
    }

    public function getArgs(): array
    {
        return [
            'valid_plots' => $this->getValidPlots(),
        ];
    }

    /**
     * Plant one pepper on an empty adjacent plot.
     *
     * @param int    $row
     * @param int    $col
     * @param string $color   pepper colour from the player's supply
     * @param bool   $using_bonus_tile  if true, consume the extra_plant tile
     */
    #[PossibleAction]
    public function actPlant(int $row, int $col, string $color,
                             bool $using_bonus_tile, int $activePlayerId): string
    {
        // Validate colour
        if (!in_array($color, Material::PEPPERS, true)) {
            throw new UserException("Invalid pepper colour");
        }

        // On a second plant the bonus tile must be in play
        $plantedThisTurn = (int) $this->game->globals->get('planted_this_turn');
        if ($plantedThisTurn > 0 && !$using_bonus_tile) {
            throw new UserException("You can only plant one pepper per round without the bonus tile");
        }
        if ($using_bonus_tile && $plantedThisTurn === 0) {
            throw new UserException("Declare the bonus tile on the second plant, not the first");
        }

        if ($using_bonus_tile) {
            $tile = static::getObjectFromDB(
                "SELECT * FROM `bonus_tile`
                 WHERE `player_id` = $activePlayerId
                   AND `tile_type` = 'extra_plant' AND `used` = 0 LIMIT 1"
            );
            if (!$tile) {
                throw new UserException("You do not have the extra-plant bonus tile");
            }
            static::DbQuery(
                "UPDATE `bonus_tile` SET `used` = 1 WHERE `tile_id` = {$tile['tile_id']}"
            );
        }

        // Validate the plot is valid
        $validPlots = $this->getValidPlots();
        $key = "{$row}_{$col}";
        if (!isset($validPlots[$key])) {
            throw new UserException("That plot is not a valid planting location");
        }

        // Check the player has this pepper
        $have = (int) static::getUniqueValueFromDB(
            "SELECT `count` FROM `player_pepper`
             WHERE `player_id` = $activePlayerId AND `pepper_color` = '$color'"
        );
        if ($have < 1) {
            throw new UserException("You do not have a $color pepper to plant");
        }

        // Plant it
        static::DbQuery(
            "INSERT INTO `pepper_field` (`plot_row`, `plot_col`, `pepper_color`)
             VALUES ($row, $col, '$color')"
        );
        // Deduct from player supply
        static::DbQuery(
            "UPDATE `player_pepper` SET `count` = `count` - 1
             WHERE `player_id` = $activePlayerId AND `pepper_color` = '$color'"
        );

        $this->bga->notify->all('pepperPlanted',
            clienttranslate('${player_name} plants a ${color} pepper'), [
            'player_id' => $activePlayerId,
            'row'       => $row,
            'col'       => $col,
            'color'     => $color,
            'i18n'      => ['color'],
        ]);

        // Award plaque (max one per round, only on the first plant)
        $plaqueEarned = (int) $this->game->globals->get('plaque_earned_this_turn');
        if ($plaqueEarned === 0) {
            $plaque = $this->game->awardPlaque($activePlayerId, $color);
            if ($plaque) {
                $this->game->globals->set('plaque_earned_this_turn', 1);
                $this->bga->notify->all('plaqueAwarded',
                    clienttranslate('${player_name} earns an Award Plaque worth ${points} points!'), [
                    'player_id'   => $activePlayerId,
                    'plaque_id'   => $plaque['plaque_id'],
                    'pepper_color'=> $plaque['pepper_color'],
                    'points'      => (int) $plaque['points'],
                ]);
            }
        }

        // If this was the first plant and the player has (or might use) the bonus tile,
        // stay in state so they can optionally plant again.
        $this->game->globals->set('planted_this_turn', $plantedThisTurn + 1);

        // After second plant (or if no bonus tile used on first), advance
        if ($plantedThisTurn >= 1 || !$this->playerHasBonusTile($activePlayerId, 'extra_plant')) {
            return $this->advanceOrNext($activePlayerId);
        }

        // Stay in Planting so the player can choose to use the bonus tile
        return Planting::class;
    }

    /**
     * Player passes on using the bonus-tile second plant.
     */
    #[PossibleAction]
    public function actSkipBonusPlant(int $activePlayerId): string
    {
        if ((int) $this->game->globals->get('planted_this_turn') === 0) {
            throw new UserException("You must plant at least one pepper");
        }
        return $this->advanceOrNext($activePlayerId);
    }

    // -------------------------------------------------------------------------

    private function advanceOrNext(int $activePlayerId): string
    {
        $ordered = $this->game->getPlayerIdsInTurnOrder();
        $next    = $this->game->advancePhasePlayer($ordered);

        if ($next !== null) {
            $this->game->globals->set('planted_this_turn', 0);
            $this->game->globals->set('plaque_earned_this_turn', 0);
            $this->game->gamestate->changeActivePlayer($next);
            return Planting::class;
        }

        // All players planted — move to Harvesting (reverse order)
        $this->game->globals->set('phase_player_idx', 0);
        return Harvesting::class;
    }

    private function getValidPlots(): array
    {
        // Collect all occupied plot positions
        $occupied = $this->game->getCollectionFromDb(
            "SELECT CONCAT(`plot_row`,'_',`plot_col`) AS `key`,
                    `plot_row` AS `row`, `plot_col` AS `col`
             FROM `pepper_field`"
        );

        $occupiedSet = array_fill_keys(array_column($occupied, 'key'), true);

        // Any empty plot adjacent (H/V) to an occupied one is valid
        $valid = [];
        foreach ($occupied as $p) {
            $r = (int) $p['row'];
            $c = (int) $p['col'];
            foreach ([[-1,0],[1,0],[0,-1],[0,1]] as [$dr, $dc]) {
                $nr  = $r + $dr;
                $nc  = $c + $dc;
                $key = "{$nr}_{$nc}";
                if ($nr < 0 || $nr >= Material::FIELD_ROWS) continue;
                if ($nc < 0 || $nc >= Material::FIELD_COLS) continue;
                if (!isset($occupiedSet[$key])) {
                    $valid[$key] = ['row' => $nr, 'col' => $nc];
                }
            }
        }

        return $valid;
    }

    private function playerHasBonusTile(int $playerId, string $type): bool
    {
        return (bool) static::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM `bonus_tile`
             WHERE `player_id` = $playerId AND `tile_type` = '$type' AND `used` = 0"
        );
    }

    public function zombie(int $playerId): string
    {
        // Plant the first available colour in the first valid plot
        $validPlots = $this->getValidPlots();
        if (empty($validPlots)) {
            return $this->advanceOrNext($playerId);
        }
        $plot = reset($validPlots);

        $peppers = $this->game->getCollectionFromDb(
            "SELECT `pepper_color` FROM `player_pepper`
             WHERE `player_id` = $playerId AND `count` > 0 LIMIT 1"
        );
        if (empty($peppers)) {
            return $this->advanceOrNext($playerId);
        }
        $color = reset($peppers)['pepper_color'];
        return $this->actPlant((int)$plot['row'], (int)$plot['col'], $color, false, $playerId);
    }
}
