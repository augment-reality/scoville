<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Scovillenew\Game;
use Bga\Games\Scovillenew\Material;

/**
 * TIME CHECK phase — automated state that checks whether to continue into a
 * new round, transition from Morning to Afternoon, or end the game.
 *
 * Morning → Afternoon: when fewer Market cards remain than numPlayers.
 * Afternoon end:       when either display (Market OR Recipe) has fewer cards
 *                      than numPlayers.  If BOTH are low, end immediately.
 * Forced early end:    when Recipe display drops below numPlayers during Morning.
 */
class TimeCheck extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 50,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    public function onEnteringState(): void
    {
        $numPlayers  = count($this->game->loadPlayersBasicInfos());
        $isAfternoon = (bool) $this->game->globals->get('is_afternoon');
        $finalMode   = (int)  $this->game->globals->get('final_round_mode');

        // If we already triggered "one more round", game ends now
        if ($finalMode === 1) {
            $this->game->globals->set('final_round_mode', 2);
            $this->game->gamestate->nextState('endGame');
            return;
        }

        $marketRemaining = $this->game->marketCards->countCardInLocation('display');
        $recipeRemaining = $this->game->recipeCards->countCardInLocation('display');

        if (!$isAfternoon) {
            $this->checkMorning($numPlayers, $marketRemaining, $recipeRemaining);
        } else {
            $this->checkAfternoon($numPlayers, $marketRemaining, $recipeRemaining);
        }
    }

    // -------------------------------------------------------------------------

    private function checkMorning(int $n, int $market, int $recipe): void
    {
        if ($market >= $n) {
            // Market OK — now check recipe
            if ($recipe < $n) {
                // Recipe running out: skip Afternoon entirely, play one final round
                $this->triggerFinalRound(clienttranslate('Recipe display is low — skipping Afternoon! One final round.'));
                return;
            }
            // Both fine — morning continues
            $this->startNewRound();
            return;
        }

        // Market low → always transition to Afternoon regardless of recipe count
        $this->bga->notify->all('afternoonBegins',
            clienttranslate('Morning is over — the Afternoon begins!'), []);

        // Discard remaining morning Market cards
        $morning = $this->game->marketCards->getCardsInLocation('display');
        foreach ($morning as $card) {
            $this->game->marketCards->moveCard((int)$card['id'], 'discard');
        }

        // Refill Farmers' Market with Afternoon cards (same count as morning start)
        $displayCount = Material::DISPLAY_COUNTS[$n]['market'];
        $this->game->marketCards->pickCardsForLocation($displayCount, 'deck_afternoon', 'display');

        // Morning Auction deck is retired — afternoon deck becomes active automatically
        // (refillAuctionDisplay checks is_afternoon to choose the right deck)

        $this->game->globals->set('is_afternoon', 1);
        $this->startNewRound();
    }

    private function checkAfternoon(int $n, int $market, int $recipe): void
    {
        $marketLow = $market < $n;
        $recipeLow = $recipe < $n;

        if ($marketLow && $recipeLow) {
            // Both low — end immediately (no final round)
            $this->game->globals->set('final_round_mode', 2);
            $this->game->gamestate->nextState('endGame');
            return;
        }

        if ($marketLow || $recipeLow) {
            $this->triggerFinalRound("Display running low — one final round!");
            return;
        }

        $this->startNewRound();
    }

    private function triggerFinalRound(string $msg): void
    {
        $this->bga->notify->all('finalRound', clienttranslate($msg), []);
        $this->game->globals->set('final_round_mode', 1);
        $this->startNewRound();
    }

    private function startNewRound(): void
    {
        $round = (int) $this->game->globals->get('round_number') + 1;
        $this->game->globals->set('round_number', $round);
        $this->game->globals->set('phase_player_idx', 0);

        $this->bga->notify->all('newRound',
            clienttranslate('Round ${round} begins'), [
            'round' => $round,
        ]);

        $this->game->gamestate->nextState('auction');
    }
}
