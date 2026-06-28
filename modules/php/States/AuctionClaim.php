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
 * AUCTION phase, step 4 — each player (in new turn order) claims one card
 * from the Auction House and receives its pepper(s).
 */
class AuctionClaim extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 15,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function onEnteringState(int $activePlayerId): void
    {
        // First player in this phase: reset index and activate them
        $idx = (int) $this->game->globals->get('phase_player_idx');
        if ($idx === 0) {
            $ordered = $this->game->getPlayerIdsInTurnOrder();
            $this->game->gamestate->changeActivePlayer($ordered[0]);
        }
    }

    public function getArgs(): array
    {
        return [
            'auction_display' => array_values(
                $this->game->auctionCards->getCardsInLocation('display')
            ),
        ];
    }

    #[PossibleAction]
    public function actClaimAuctionCard(int $card_id, int $activePlayerId, array $args): string
    {
        // Validate the card is in the display
        $card = $this->game->auctionCards->getCard($card_id);
        if (!$card || $card['location'] !== 'display') {
            throw new UserException("That card is not available");
        }

        $defIdx  = (int) $card['type_arg'];
        $def     = Material::AUCTION_CARD_DEFS[$defIdx];
        $peppers = $def['peppers'];

        // Move card to discard
        $this->game->auctionCards->moveCard($card_id, 'discard');

        // Give peppers to player
        $pepperList = [];
        foreach ($peppers as $color => $count) {
            for ($i = 0; $i < $count; $i++) {
                $pepperList[] = $color;
            }
        }
        $this->game->givePeppers($activePlayerId, $pepperList);

        $this->bga->notify->all('auctionCardClaimed',
            clienttranslate('${player_name} claims an auction card'), [
            'player_id' => $activePlayerId,
            'card_id'   => $card_id,
            'peppers'   => $pepperList,
        ]);

        return $this->advanceOrNext($activePlayerId);
    }

    private function advanceOrNext(int $activePlayerId): string
    {
        $ordered = $this->game->getPlayerIdsInTurnOrder();
        $next    = $this->game->advancePhasePlayer($ordered);

        if ($next !== null) {
            $this->game->gamestate->changeActivePlayer($next);
            return AuctionClaim::class;
        }

        // All claimed — refill display and start Planting
        $numPlayers = count($ordered);
        $this->game->refillAuctionDisplay($numPlayers);
        $this->game->globals->set('phase_player_idx', 0);
        return Planting::class;
    }

    public function zombie(int $playerId): string
    {
        // Pick the first available card
        $display = $this->game->auctionCards->getCardsInLocation('display');
        if (empty($display)) {
            return $this->advanceOrNext($playerId);
        }
        $card = reset($display);
        return $this->actClaimAuctionCard((int) $card['id'], $playerId, $this->getArgs());
    }
}
