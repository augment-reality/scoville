<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Scovillenew\Game;

/**
 * AUCTION phase, step 1 — all players simultaneously bid coins for turn order.
 * Skipped in round 1 (turn order is random from setup).
 */
class AuctionBid extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 10,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
        );
    }

    public function onEnteringState(): void
    {
        // Reset bid flags for all players
        static::DbQuery("UPDATE `player` SET `player_bid` = 0, `player_bid_done` = 0");
    }

    public function getArgs(): array
    {
        // Each active player can see their own coins; other bids are hidden.
        // Return per-player coin counts so the client can show the player
        // how much they can spend.
        return [
            'player_coins' => $this->game->getCollectionFromDb(
                "SELECT `player_id` AS `id`, `player_coins` AS `coins` FROM `player`"
            ),
        ];
    }

    /**
     * Each player submits their bid secretly.
     */
    #[PossibleAction]
    public function actBid(int $bid_amount, int $activePlayerId): void
    {
        // Validate
        $coins = (int) static::getUniqueValueFromDB(
            "SELECT `player_coins` FROM `player` WHERE `player_id` = $activePlayerId"
        );
        if ($bid_amount < 0 || $bid_amount > $coins) {
            throw new UserException("Invalid bid amount");
        }

        // Store the bid and mark player done
        static::DbQuery(
            "UPDATE `player`
             SET `player_bid` = $bid_amount, `player_bid_done` = 1
             WHERE `player_id` = $activePlayerId"
        );

        // Remove this player from the active set
        $this->game->gamestate->setPlayerNonMultiactive($activePlayerId, '');

        // Check if ALL players have bid
        $remaining = (int) static::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM `player` WHERE `player_bid_done` = 0"
        );
        if ($remaining === 0) {
            // Transition to resolve
            $this->game->gamestate->nextState('resolve');
        }
    }

    public function zombie(int $playerId): void
    {
        // Zombie bids 0
        $this->actBid(0, $playerId);
    }
}