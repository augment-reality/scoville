<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\Scovillenew\Game;
use Bga\Games\Scovillenew\Material;

/**
 * END GAME — compute final scores:
 *   + Points on fulfilled Market cards     (already in player_score live)
 *   + Points on fulfilled Recipe cards     (already in player_score live)
 *   + Points on earned Award Plaques
 *   + 4 points per unused Bonus Tile
 *   + floor(coins / 3) points
 *
 * Tie-breaker: most coins (stored in player_score_aux).
 */
class EndScore extends GameState
{
    function __construct(protected Game $game)
    {
        parent::__construct($game,
            id: 98,
            type: StateType::GAME,
        );
    }

    public function onEnteringState(): void
    {
        $players = $this->game->loadPlayersBasicInfos();

        foreach ($players as $playerId => $player) {
            $pid = (int) $playerId;

            // Points from Award Plaques
            $plaquePoints = (int) static::getUniqueValueFromDB(
                "SELECT COALESCE(SUM(`points`), 0) FROM `award_plaque` WHERE `player_id` = $pid"
            );

            // Points from unused Bonus Tiles (4 each)
            $unusedTiles  = (int) static::getUniqueValueFromDB(
                "SELECT COUNT(*) FROM `bonus_tile` WHERE `player_id` = $pid AND `used` = 0"
            );
            $tilePoints   = $unusedTiles * Material::BONUS_TILE_END_POINTS;

            // Points from coins: floor(coins/3)
            $coins       = (int) static::getUniqueValueFromDB(
                "SELECT `player_coins` FROM `player` WHERE `player_id` = $pid"
            );
            $coinPoints  = (int) floor($coins / 3);

            $bonus = $plaquePoints + $tilePoints + $coinPoints;
            $this->bga->playerScore->inc($pid, $bonus);

            // Set tie-breaker (coins)
            static::DbQuery(
                "UPDATE `player` SET `player_score_aux` = $coins WHERE `player_id` = $pid"
            );

            $this->bga->notify->all('finalScore',
                clienttranslate('${player_name} scores ${pts} bonus points (plaques: ${plaques}, tiles: ${tiles}, coins: ${coins_pts})'), [
                'player_id'  => $pid,
                'pts'        => $bonus,
                'plaques'    => $plaquePoints,
                'tiles'      => $tilePoints,
                'coins_pts'  => $coinPoints,
            ]);
        }

        $this->game->gamestate->nextState('endGame');
    }
}
