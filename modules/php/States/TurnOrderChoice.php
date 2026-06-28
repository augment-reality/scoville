<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\Scovillenew\Game;

/**
 * AUCTION phase, step 3 — each bidder (in descending bid order) chooses
 * their slot on the turn-order track.
 */
class TurnOrderChoice extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 12,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function getArgs(): array
    {
        return [
            'available_slots' => $this->game->globals->get('available_slots'),
        ];
    }

    #[PossibleAction]
    public function actChooseSlot(int $slot, int $activePlayerId, array $args): string
    {
        $availableSlots = $args['available_slots'];

        if (!in_array($slot, $availableSlots, true)) {
            throw new UserException("That turn order slot is not available");
        }

        // Pay the bid
        $bid = (int) static::getUniqueValueFromDB(
            "SELECT `player_bid` FROM `player` WHERE `player_id` = $activePlayerId"
        );
        $this->game->addCoins($activePlayerId, -$bid);

        // Assign the chosen slot
        static::DbQuery(
            "UPDATE `player` SET `player_turn_order` = $slot WHERE `player_id` = $activePlayerId"
        );

        // Remove slot from available list
        $remaining = array_values(array_filter($availableSlots, fn($s) => $s !== $slot));
        $this->game->globals->set('available_slots', $remaining);

        $this->bga->notify->all('turnOrderChosen',
            clienttranslate('${player_name} chooses turn order position ${slot}'), [
            'player_id'   => $activePlayerId,
            'slot'        => $slot,
        ]);

        // Advance to next bidder
        $bidOrder = $this->game->globals->get('bid_order');
        $idx      = (int) $this->game->globals->get('bid_player_idx') + 1;
        $this->game->globals->set('bid_player_idx', $idx);

        if (isset($bidOrder[$idx])) {
            $this->game->gamestate->changeActivePlayer($bidOrder[$idx]);
            return TurnOrderChoice::class;
        }

        // All bidders done — proceed to AuctionClaim
        $this->game->globals->set('phase_player_idx', 0);
        return AuctionClaim::class;
    }

    public function zombie(int $playerId): string
    {
        // Pick the first available slot
        $args = $this->getArgs();
        $slot = $args['available_slots'][0] ?? 1;
        return $this->actChooseSlot($slot, $playerId, $args);
    }
}