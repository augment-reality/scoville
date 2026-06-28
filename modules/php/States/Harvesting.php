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
 * HARVESTING phase — each player (in REVERSE turn order) moves their farmer
 * up to 3 steps along plot paths, crossbreeding peppers at each step that
 * lands between two planted plots.
 *
 * The farmer's position is stored as a notch ID in `player.farmer_notch`.
 * Material::NOTCH_STAR (-1) = at the star (first round only).
 *
 * Movement rules enforced:
 *   - At least 1 step, at most 3 (or 4 with the extra_step bonus tile).
 *   - Cannot revisit any notch visited earlier this turn.
 *   - Cannot step through an opponent's farmer (same notch blocking).
 *   - Must move at least 1 step (zombie will force a step if blocked).
 *
 * The "double_back" bonus tile allows reversing direction once per turn;
 * the server tracks this via the harvest_path and harvest_can_doubleback globals.
 */
class Harvesting extends GameState
{
    const MAX_STEPS         = 3;
    const MAX_STEPS_BONUS   = 4;

    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 30,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function onEnteringState(int $activePlayerId): void
    {
        // First time entering this phase: activate the last player in turn order
        $idx = (int) $this->game->globals->get('phase_player_idx');
        if ($idx === 0) {
            $reversed = $this->game->getPlayerIdsInReverseOrder();
            $this->game->gamestate->changeActivePlayer($reversed[0]);
        }

        // Reset per-turn harvest state
        $this->game->globals->set('harvest_steps', 0);
        $this->game->globals->set('harvest_path', []);
        $this->game->globals->set('harvest_used_extra_step', 0);
        $this->game->globals->set('harvest_used_double_back', 0);
    }

    public function getArgs(): array
    {
        $steps        = (int) $this->game->globals->get('harvest_steps');
        $path         = (array) $this->game->globals->get('harvest_path');
        $usedExtra    = (bool) $this->game->globals->get('harvest_used_extra_step');
        $maxSteps     = $usedExtra ? self::MAX_STEPS_BONUS : self::MAX_STEPS;

        return [
            'steps_taken'       => $steps,
            'max_steps'         => $maxSteps,
            'can_stop'          => $steps >= 1,
            'can_step'          => $steps < $maxSteps,
            'valid_notches'     => $steps < $maxSteps ? $this->getValidNextNotches() : [],
            'harvest_path'      => $path,
        ];
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Move the farmer one step to the given notch.
     */
    #[PossibleAction]
    public function actMoveFarmer(int $notch_id, int $activePlayerId, array $args): string
    {
        if (!$args['can_step']) {
            throw new UserException("You have already used all your steps");
        }

        $validNotches = $args['valid_notches'];
        if (!in_array($notch_id, $validNotches, true)) {
            throw new UserException("That is not a valid step");
        }

        // Move farmer
        static::DbQuery(
            "UPDATE `player` SET `farmer_notch` = $notch_id WHERE `player_id` = $activePlayerId"
        );

        // Update path
        $path   = (array) $this->game->globals->get('harvest_path');
        $path[] = $notch_id;
        $this->game->globals->set('harvest_path', $path);
        $steps = count($path);
        $this->game->globals->set('harvest_steps', $steps);

        // Crossbreed if between two planted plots
        $peppers = $this->crossbreedAtNotch($notch_id, $activePlayerId);

        $this->bga->notify->all('farmerMoved',
            clienttranslate('${player_name} moves their farmer'), [
            'player_id'       => $activePlayerId,
            'notch_id'        => $notch_id,
            'peppers_gained'  => $peppers,
        ]);

        // Auto-end if max steps reached and no extra_step tile available
        $usedExtra = (bool) $this->game->globals->get('harvest_used_extra_step');
        $maxSteps  = $usedExtra ? self::MAX_STEPS_BONUS : self::MAX_STEPS;

        if ($steps >= $maxSteps) {
            return $this->advanceOrNext($activePlayerId);
        }

        return Harvesting::class;
    }

    /**
     * Player voluntarily ends their harvest (after at least 1 step).
     */
    #[PossibleAction]
    public function actEndHarvest(int $activePlayerId, array $args): string
    {
        if (!$args['can_stop']) {
            throw new UserException("You must move at least one step");
        }
        return $this->advanceOrNext($activePlayerId);
    }

    /**
     * Use the extra_step bonus tile to take a 4th step.
     */
    #[PossibleAction]
    public function actUseExtraStep(int $activePlayerId, array $args): string
    {
        if ((int) $args['steps_taken'] < self::MAX_STEPS) {
            throw new UserException("Use the extra step tile only after taking 3 steps");
        }
        if ($this->game->globals->get('harvest_used_extra_step')) {
            throw new UserException("Already used the extra step tile this turn");
        }

        $tile = static::getObjectFromDB(
            "SELECT * FROM `bonus_tile`
             WHERE `player_id` = $activePlayerId AND `tile_type` = 'extra_step' AND `used` = 0 LIMIT 1"
        );
        if (!$tile) {
            throw new UserException("You do not have the extra-step bonus tile");
        }

        static::DbQuery("UPDATE `bonus_tile` SET `used` = 1 WHERE `tile_id` = {$tile['tile_id']}");
        $this->game->globals->set('harvest_used_extra_step', 1);

        $this->bga->notify->all('bonusTileUsed',
            clienttranslate('${player_name} uses the Extra Step tile'), [
            'player_id' => $activePlayerId,
            'tile_type' => 'extra_step',
        ]);

        return Harvesting::class;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getValidNextNotches(): array
    {
        // Get current farmer position
        $rows = $this->game->getCollectionFromDb(
            "SELECT `player_id`, `farmer_notch` FROM `player`"
        );

        // All occupied notches (other farmers the active player cannot pass through)
        $activeRow     = null;
        $blockedNotches = [];
        foreach ($rows as $row) {
            if ($row['player_id'] === $this->getActivePlayerId()) {
                $activeRow = $row;
            } else {
                $blockedNotches[] = (int) $row['farmer_notch'];
            }
        }

        $currentNotch = $activeRow ? (int) $activeRow['farmer_notch'] : Material::NOTCH_STAR;
        $path         = (array) $this->game->globals->get('harvest_path');

        $adjacent = Material::adjacentNotches($currentNotch);

        return array_values(array_filter($adjacent, function ($n) use ($path, $blockedNotches) {
            // Cannot revisit a notch already in path this turn
            if (in_array($n, $path, true)) return false;
            // Cannot land on another farmer
            if (in_array($n, $blockedNotches, true)) return false;
            return true;
        }));
    }

    private function getActivePlayerId(): int
    {
        return (int) $this->game->getActivePlayerId();
    }

    /**
     * Resolve crossbreeding at the given notch.
     * Returns array of pepper colour strings gained (may be empty).
     */
    private function crossbreedAtNotch(int $notchId, int $playerId): array
    {
        $plots = Material::notchPlots($notchId);
        if (count($plots) < 2) return [];

        [$pA, $pB] = $plots;

        $colorA = static::getUniqueValueFromDB(
            "SELECT `pepper_color` FROM `pepper_field`
             WHERE `plot_row` = {$pA['row']} AND `plot_col` = {$pA['col']}"
        );
        $colorB = static::getUniqueValueFromDB(
            "SELECT `pepper_color` FROM `pepper_field`
             WHERE `plot_row` = {$pB['row']} AND `plot_col` = {$pB['col']}"
        );

        if (!$colorA || !$colorB) return []; // at least one plot is empty

        $result = Material::BREEDING_CHART[$colorA][$colorB] ?? [];
        if (!empty($result)) {
            $this->game->givePeppers($playerId, $result);
        }
        return $result;
    }

    private function advanceOrNext(int $activePlayerId): string
    {
        $reversed = $this->game->getPlayerIdsInReverseOrder();
        $next     = $this->game->advancePhasePlayer($reversed);

        if ($next !== null) {
            $this->game->globals->set('harvest_steps', 0);
            $this->game->globals->set('harvest_path', []);
            $this->game->globals->set('harvest_used_extra_step', 0);
            $this->game->globals->set('harvest_used_double_back', 0);
            $this->game->gamestate->changeActivePlayer($next);
            return Harvesting::class;
        }

        $this->game->globals->set('phase_player_idx', 0);
        return Fulfillment::class;
    }

    public function zombie(int $playerId): string
    {
        $args = $this->getArgs();

        // Try to take at least one step
        if ($args['can_step'] && !empty($args['valid_notches'])) {
            return $this->actMoveFarmer($args['valid_notches'][0], $playerId, $args);
        }

        // Can't move at all — skip if already moved, otherwise skip with 0 steps
        return $this->advanceOrNext($playerId);
    }
}
