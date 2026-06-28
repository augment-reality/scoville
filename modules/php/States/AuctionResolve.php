<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Scovillenew\Game;

/**
 * AUCTION phase, step 2 — reveal all bids and determine the order in which
 * players will choose their turn-order slots.
 *
 * Zero-bidders are auto-assigned to the lowest available slots (in previous
 * turn order), so only players who bid > 0 enter TurnOrderChoice.
 */
class AuctionResolve extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 11,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): void
    {
        // Collect all bids
        $rows = $this->game->getCollectionFromDb(
            "SELECT `player_id`, `player_bid`, `player_turn_order`
             FROM `player` ORDER BY `player_turn_order` ASC"
        );

        // Notify all players of the revealed bids
        $bidData = [];
        foreach ($rows as $row) {
            $bidData[] = [
                'player_id' => $row['player_id'],
                'bid'       => (int) $row['player_bid'],
            ];
        }
        $this->bga->notify->all('bidsRevealed', clienttranslate('Bids are revealed!'), [
            'bids' => $bidData,
        ]);

        // Separate bidders (>0) from zero-bidders, preserving prev turn-order for ties
        $bidders     = [];
        $zeroBidders = [];
        foreach ($rows as $row) {
            if ((int) $row['player_bid'] > 0) {
                $bidders[] = $row;
            } else {
                $zeroBidders[] = $row;
            }
        }

        // Sort active bidders: highest bid first; tie → earlier prev turn order first
        usort($bidders, function ($a, $b) {
            if ($b['player_bid'] !== $a['player_bid']) {
                return $b['player_bid'] <=> $a['player_bid'];
            }
            return $a['player_turn_order'] <=> $b['player_turn_order'];
        });

        // All available slot numbers (1 … numPlayers)
        $numPlayers   = count($rows);
        $allSlots     = range(1, $numPlayers);
        $usedSlots    = [];

        // Zero-bidders get the lowest-numbered available slots (in prev turn order)
        // We'll assign their slots now so TurnOrderChoice only handles actual bidders.
        foreach ($zeroBidders as $row) {
            $slot = array_shift($allSlots); // lowest available
            $pid  = $row['player_id'];
            static::DbQuery(
                "UPDATE `player` SET `player_turn_order` = $slot WHERE `player_id` = $pid"
            );
            $usedSlots[] = $slot;
            $this->bga->notify->all('turnOrderChosen', clienttranslate('${player_name} (bid 0) is assigned turn order ${slot}'), [
                'player_id'   => $pid,
                'slot'        => $slot,
            ]);
        }

        // Build bid order for TurnOrderChoice
        $bidOrder = array_column($bidders, 'player_id');

        if (empty($bidOrder)) {
            // All players bid 0 — order already resolved; go to AuctionClaim
            $this->game->globals->set('phase_player_idx', 0);
            $this->game->gamestate->nextState('claim');
            return;
        }

        $this->game->globals->set('bid_order', $bidOrder);
        $this->game->globals->set('bid_player_idx', 0);
        // Remaining available slots for active bidders
        $this->game->globals->set('available_slots', $allSlots);

        // Activate first bidder
        $this->game->gamestate->changeActivePlayer($bidOrder[0]);
        $this->game->gamestate->nextState('choose');
    }
}