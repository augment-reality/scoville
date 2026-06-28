<?php

declare(strict_types=1);

namespace Bga\Games\Scovillenew;

/**
 * All static game data for Scoville.
 *
 * *** BREEDING CHART NOTE ***
 * Rules applied from rulebook:
 *   Primary×Same → 2×itself; Primary×DiffPrimary → Secondary
 *   Primary×Secondary → Brown; Primary/Sec ×Black/White → 1×Primary/Sec
 *   Secondary×Same → Black; Secondary×DiffSec → White
 *   White×White → 2 White; Black×Black → 2 Black; Brown×Brown → 2 Brown
 *   Brown×Black → Black; Brown×White → White
 *   Phantom×Primary → White; Phantom×Secondary → Black
 *   Phantom×Brown → 2 Brown; Phantom×White → 2 White; Phantom×Black → 2 Black
 *   Phantom×Phantom → 2 Phantom
 *   Black×White → Phantom; Brown×Primary/Sec → nothing
 * No remaining TODO_VERIFY cells in the breeding chart.
 *
 * *** CARD DATA NOTE ***
 * Auction, Market, and Recipe card definitions below are
 * representative placeholders. Replace with exact publisher data.
 *
 * *** FIELD LAYOUT NOTE ***
 * The pepper field grid dimensions and starting-plot coordinates
 * are approximations. Update FIELD_ROWS, FIELD_COLS, STARTING_PLOTS,
 * STAR_EDGES, and VALID_PLOTS once board artwork is finalised.
 */
class Material
{
    // -------------------------------------------------------------------------
    // Pepper colours
    // -------------------------------------------------------------------------

    // Primary: red, yellow, blue
    // Secondary: orange (R+Y), green (Y+B), purple (R+B)
    // Special: white, brown, black, phantom
    const PEPPERS = ['red', 'yellow', 'blue', 'orange', 'green', 'purple', 'white', 'brown', 'black', 'phantom'];

    const PEPPER_TIER = [
        'red'     => 1,
        'yellow'  => 1,
        'blue'    => 1,
        'orange'  => 2,
        'green'   => 2,
        'purple'  => 2,
        'white'   => 3,
        'brown'   => 3,
        'black'   => 4,
        'phantom' => 4,
    ];

    // -------------------------------------------------------------------------
    // Breeding chart
    // BREEDING_CHART[colorA][colorB] => array of resulting pepper colours.
    // Empty array = X (nothing harvested).
    // The chart is symmetric; both [A][B] and [B][A] are defined for convenience.
    // -------------------------------------------------------------------------

    // Rules applied:
    //  Primary×Same       → two of itself
    //  Primary×DiffPrimary→ Secondary  (R+Y=O, R+B=P, Y+B=G)
    //  Primary×Secondary  → Brown
    //  Primary/Sec×Black  → one of Primary/Secondary
    //  Primary/Sec×White  → one of Primary/Secondary
    //  Secondary×Same     → Black
    //  Secondary×DiffSec  → White
    //  White×White        → two Whites
    //  Black×Black        → two Blacks
    //  Brown×Brown        → two Browns
    //  Brown×Black        → Black
    //  Brown×White        → White
    //  Phantom×Primary    → White
    //  Phantom×Secondary  → Black
    //  Phantom×Brown      → two Browns
    //  Phantom×White      → two Whites
    //  Phantom×Black      → two Blacks
    //  Phantom×Phantom    → two Phantoms
    //  Uncovered pairs (Primary/Sec×Brown, Black×White): [] TODO_VERIFY
    const BREEDING_CHART = [
        'red' => [
            'red'     => ['red', 'red'],
            'yellow'  => ['orange'],
            'blue'    => ['purple'],
            'orange'  => ['brown'],
            'green'   => ['brown'],
            'purple'  => ['brown'],
            'white'   => ['red'],
            'brown'   => [],
            'black'   => ['red'],
            'phantom' => ['white'],
        ],
        'yellow' => [
            'red'     => ['orange'],
            'yellow'  => ['yellow', 'yellow'],
            'blue'    => ['green'],
            'orange'  => ['brown'],
            'green'   => ['brown'],
            'purple'  => ['brown'],
            'white'   => ['yellow'],
            'brown'   => [],
            'black'   => ['yellow'],
            'phantom' => ['white'],
        ],
        'blue' => [
            'red'     => ['purple'],
            'yellow'  => ['green'],
            'blue'    => ['blue', 'blue'],
            'orange'  => ['brown'],
            'green'   => ['brown'],
            'purple'  => ['brown'],
            'white'   => ['blue'],
            'brown'   => [],
            'black'   => ['blue'],
            'phantom' => ['white'],
        ],
        'orange' => [
            'red'     => ['brown'],
            'yellow'  => ['brown'],
            'blue'    => ['brown'],
            'orange'  => ['black'],
            'green'   => ['white'],
            'purple'  => ['white'],
            'white'   => ['orange'],
            'brown'   => [],
            'black'   => ['orange'],
            'phantom' => ['black'],
        ],
        'green' => [
            'red'     => ['brown'],
            'yellow'  => ['brown'],
            'blue'    => ['brown'],
            'orange'  => ['white'],
            'green'   => ['black'],
            'purple'  => ['white'],
            'white'   => ['green'],
            'brown'   => [],
            'black'   => ['green'],
            'phantom' => ['black'],
        ],
        'purple' => [
            'red'     => ['brown'],
            'yellow'  => ['brown'],
            'blue'    => ['brown'],
            'orange'  => ['white'],
            'green'   => ['white'],
            'purple'  => ['black'],
            'white'   => ['purple'],
            'brown'   => [],
            'black'   => ['purple'],
            'phantom' => ['black'],
        ],
        'white' => [
            'red'     => ['red'],
            'yellow'  => ['yellow'],
            'blue'    => ['blue'],
            'orange'  => ['orange'],
            'green'   => ['green'],
            'purple'  => ['purple'],
            'white'   => ['white', 'white'],
            'brown'   => ['white'],
            'black'   => ['phantom'],
            'phantom' => ['white', 'white'],
        ],
        'brown' => [
            'red'     => [],
            'yellow'  => [],
            'blue'    => [],
            'orange'  => [],
            'green'   => [],
            'purple'  => [],
            'white'   => ['white'],
            'brown'   => ['brown', 'brown'],
            'black'   => ['black'],
            'phantom' => ['brown', 'brown'],
        ],
        'black' => [
            'red'     => ['red'],
            'yellow'  => ['yellow'],
            'blue'    => ['blue'],
            'orange'  => ['orange'],
            'green'   => ['green'],
            'purple'  => ['purple'],
            'white'   => ['phantom'],
            'brown'   => ['black'],
            'black'   => ['black', 'black'],
            'phantom' => ['black', 'black'],
        ],
        'phantom' => [
            'red'     => ['white'],
            'yellow'  => ['white'],
            'blue'    => ['white'],
            'orange'  => ['black'],
            'green'   => ['black'],
            'purple'  => ['black'],
            'white'   => ['white', 'white'],
            'brown'   => ['brown', 'brown'],
            'black'   => ['black', 'black'],
            'phantom' => ['phantom', 'phantom'],
        ],
    ];

    // -------------------------------------------------------------------------
    // Pepper field geometry
    //
    // The field is a 7-row × 10-column grid of square plot cells.
    // Plots occupy positions (row, col) for row∈[0,6], col∈[0,9].
    //
    // Grid vertices (corners): (i, j) for i∈[0,7], j∈[0,10]  (88 total).
    //
    // A NOTCH is the midpoint of ANY grid edge — interior (between two plots)
    // or exterior (on the outer boundary, beside one plot).
    //
    // H-notch(r, j): midpoint of the VERTICAL edge from vertex(r,j) to
    //   vertex(r+1,j).  Adjacent plots: left=(r,j-1) if j>0; right=(r,j) if j<COLS.
    //   Range: r∈[0,ROWS-1]=[0,6], j∈[0,COLS]=[0,10].  Count: 7×11 = 77.
    //   j=0 = left boundary; j=COLS = right boundary.
    //
    // V-notch(i, c): midpoint of the HORIZONTAL edge from vertex(i,c) to
    //   vertex(i,c+1).  Adjacent plots: above=(i-1,c) if i>0; below=(i,c) if i<ROWS.
    //   Range: i∈[0,ROWS]=[0,7], c∈[0,COLS-1]=[0,9].  Count: 8×10 = 80.
    //   i=0 = top boundary; i=ROWS = bottom boundary.
    //
    // Total: 77 + 80 = 157 notches.
    //
    // Notch ID encoding:
    //   H-notch(r, j) → id = r*(COLS+1) + j           ids 0 … 76
    //   V-notch(i, c) → id = H_NOTCH_COUNT + i*COLS+c  ids 77 … 156
    //   Star (round-1 start): NOTCH_STAR = -1
    //
    // Adjacency — two notches are adjacent iff their edges share a vertex:
    //   H-notch(r,j) vertices: (r,j) and (r+1,j).
    //     Via (r,  j): H(r-1,j), V(r,j-1), V(r,j)
    //     Via (r+1,j): H(r+1,j), V(r+1,j-1), V(r+1,j)
    //   V-notch(i,c) vertices: (i,c) and (i,c+1).
    //     Via (i,c  ): V(i,c-1), H(i-1,c), H(i,c)
    //     Via (i,c+1): V(i,c+1), H(i-1,c+1), H(i,c+1)
    //
    // TODO_VERIFY: STARTING_PLOTS against actual board artwork.
    // -------------------------------------------------------------------------

    const FIELD_ROWS = 7;
    const FIELD_COLS = 10;

    // Two plots pre-seeded at game start (green-outlined starting plots).
    // TODO_VERIFY exact coordinates from actual board art.
    const STARTING_PLOTS = [
        ['row' => 3, 'col' => 4],
        ['row' => 3, 'col' => 5],
    ];

    const NOTCH_STAR = -1;

    // H-notch count: ROWS*(COLS+1) = 7*11 = 77
    const H_NOTCH_COUNT = self::FIELD_ROWS * (self::FIELD_COLS + 1);
    // V-notch count: (ROWS+1)*COLS = 8*10 = 80
    const V_NOTCH_COUNT = (self::FIELD_ROWS + 1) * self::FIELD_COLS;

    /**
     * H-notch(r, j): r = plot-row [0..ROWS-1], j = vertex-col [0..COLS]
     * V-notch(i, c): i = vertex-row [0..ROWS], c = plot-col [0..COLS-1]
     */
    public static function notchId(string $type, int $r, int $c): int
    {
        if ($type === 'H') {
            return $r * (self::FIELD_COLS + 1) + $c;
        }
        return self::H_NOTCH_COUNT + $r * self::FIELD_COLS + $c;
    }

    /**
     * Returns ['type'=>'H'|'V', 'r'=>..., 'c'=>...] where:
     *   H: r=plot-row, c=vertex-col j
     *   V: r=vertex-row i, c=plot-col
     */
    public static function notchInfo(int $id): array
    {
        if ($id === self::NOTCH_STAR) {
            return ['type' => 'star', 'r' => -1, 'c' => -1];
        }
        if ($id < self::H_NOTCH_COUNT) {
            $r = intdiv($id, self::FIELD_COLS + 1);
            $j = $id % (self::FIELD_COLS + 1);
            return ['type' => 'H', 'r' => $r, 'c' => $j];
        }
        $v = $id - self::H_NOTCH_COUNT;
        $i = intdiv($v, self::FIELD_COLS);
        $c = $v % self::FIELD_COLS;
        return ['type' => 'V', 'r' => $i, 'c' => $c];
    }

    /**
     * Returns the 0, 1, or 2 plot positions adjacent to a notch.
     * Boundary notches return 1 plot; interior return 2; star returns 0.
     */
    public static function notchPlots(int $id): array
    {
        if ($id === self::NOTCH_STAR) return [];

        $info = self::notchInfo($id);
        $ROWS = self::FIELD_ROWS;
        $COLS = self::FIELD_COLS;
        $plots = [];

        if ($info['type'] === 'H') {
            $r = $info['r'];
            $j = $info['c'];            // vertex-col
            if ($j > 0)     $plots[] = ['row' => $r, 'col' => $j - 1]; // left plot
            if ($j < $COLS) $plots[] = ['row' => $r, 'col' => $j    ]; // right plot
        } else {
            $i = $info['r'];            // vertex-row
            $c = $info['c'];
            if ($i > 0)     $plots[] = ['row' => $i - 1, 'col' => $c]; // above plot
            if ($i < $ROWS) $plots[] = ['row' => $i,     'col' => $c]; // below plot
        }

        return $plots;
    }

    /**
     * Returns all notch IDs reachable in one step from notch $id.
     * Two notches are adjacent when their edges share a vertex.
     *
     * H-notch(r, j) — vertices (r,j) and (r+1,j):
     *   Via (r,  j): H(r-1,j), V(r,  j-1), V(r,  j)
     *   Via (r+1,j): H(r+1,j), V(r+1,j-1), V(r+1,j)
     *
     * V-notch(i, c) — vertices (i,c) and (i,c+1):
     *   Via (i,c  ): V(i,c-1), H(i-1,c  ), H(i,  c  )
     *   Via (i,c+1): V(i,c+1), H(i-1,c+1), H(i,  c+1)
     */
    public static function adjacentNotches(int $id): array
    {
        if ($id === self::NOTCH_STAR) {
            // From the star, farmer steps onto any left-boundary H-notch: H(r,0).
            $result = [];
            for ($r = 0; $r < self::FIELD_ROWS; $r++) {
                $result[] = self::notchId('H', $r, 0);
            }
            return $result;
        }

        $info = self::notchInfo($id);
        $type = $info['type'];
        $r    = $info['r'];   // plot-row (H) or vertex-row (V)
        $c    = $info['c'];   // vertex-col (H) or plot-col (V)

        $ROWS = self::FIELD_ROWS;
        $COLS = self::FIELD_COLS;

        $candidates = [];

        if ($type === 'H') {
            $j = $c; // vertex-col
            // Via top vertex (r, j):
            if ($r > 0)        $candidates[] = ['H', $r - 1, $j    ];
            if ($j > 0)        $candidates[] = ['V', $r,     $j - 1];
            if ($j < $COLS)    $candidates[] = ['V', $r,     $j    ];
            // Via bottom vertex (r+1, j):
            if ($r < $ROWS-1)  $candidates[] = ['H', $r + 1, $j    ];
            if ($j > 0)        $candidates[] = ['V', $r + 1, $j - 1];
            if ($j < $COLS)    $candidates[] = ['V', $r + 1, $j    ];
        } else { // V
            $i = $r; // vertex-row
            // Via left vertex (i, c):
            if ($c > 0)        $candidates[] = ['V', $i,     $c - 1];
            if ($i > 0)        $candidates[] = ['H', $i - 1, $c    ];
            if ($i < $ROWS)    $candidates[] = ['H', $i,     $c    ];
            // Via right vertex (i, c+1):
            if ($c < $COLS-1)  $candidates[] = ['V', $i,     $c + 1];
            if ($i > 0)        $candidates[] = ['H', $i - 1, $c + 1];
            if ($i < $ROWS)    $candidates[] = ['H', $i,     $c + 1];
        }

        $result = [];
        foreach ($candidates as [$t, $nr, $nc]) {
            if ($t === $type && $nr === $r && $nc === $c) continue; // skip self
            if ($t === 'H') {
                // H valid: r∈[0,ROWS-1], j∈[0,COLS]
                if ($nr < 0 || $nr >= $ROWS || $nc < 0 || $nc > $COLS) continue;
            } else {
                // V valid: i∈[0,ROWS], c∈[0,COLS-1]
                if ($nr < 0 || $nr > $ROWS || $nc < 0 || $nc >= $COLS) continue;
            }
            $result[] = self::notchId($t, $nr, $nc);
        }

        return array_values(array_unique($result));
    }

    // -------------------------------------------------------------------------
    // Bonus Action Tiles
    // -------------------------------------------------------------------------

    const BONUS_TILES = [
        'extra_plant'  => ['desc' => 'Plant a second pepper this turn'],
        'extra_step'   => ['desc' => 'Take a 4th movement step this turn'],
        'double_back'  => ['desc' => 'Reverse direction once this turn'],
    ];

    const BONUS_TILE_END_POINTS = 4; // each unplayed tile = 4 pts at end

    // -------------------------------------------------------------------------
    // Award Plaques
    //
    // Two stacks per category; higher-valued plaque sits on top (earned first).
    // 'striped' category covers Purple, Orange, and Green peppers.
    // In 2p/3p games, remove the higher-valued plaque from each stack.
    // TODO_VERIFY: exact point values from physical game.
    // -------------------------------------------------------------------------

    const AWARD_PLAQUES = [
        // [category, point_value] — index 0 = most valuable (on top of stack)
        ['color' => 'red',     'points' => 2],
        ['color' => 'red',     'points' => 1],
        ['color' => 'yellow',  'points' => 2],
        ['color' => 'yellow',  'points' => 1],
        ['color' => 'blue',    'points' => 2],
        ['color' => 'blue',    'points' => 1],
        ['color' => 'striped', 'points' => 2],  // Purple / Orange / Green
        ['color' => 'striped', 'points' => 1],
        ['color' => 'white',   'points' => 3],
        ['color' => 'white',   'points' => 2],
        ['color' => 'black',   'points' => 4],
        ['color' => 'black',   'points' => 3],
    ];

    // Which pepper colours trigger the 'striped' plaque category
    const STRIPED_PEPPERS = ['purple', 'orange', 'green'];

    // -------------------------------------------------------------------------
    // Setup: card-display sizes by player count
    //
    // TODO_VERIFY all values against the printed numbers on the actual board.
    // -------------------------------------------------------------------------

    const DISPLAY_COUNTS = [
        2 => ['market' => 5,  'recipe' => 5],
        3 => ['market' => 9,  'recipe' => 7],
        4 => ['market' => 11, 'recipe' => 9],
        5 => ['market' => 13, 'recipe' => 11],
        6 => ['market' => 15, 'recipe' => 13],
    ];

    // Auction house always has exactly one slot per player.
    public static function auctionSlots(int $numPlayers): int
    {
        return $numPlayers;
    }

    // -------------------------------------------------------------------------
    // Coin denominations (informational; actual coins are integer amounts)
    // -------------------------------------------------------------------------

    const STARTING_COINS  = 10;
    const MAX_SELL_PEPPERS = 5;  // max peppers of one colour sold per Fulfillment turn

    // -------------------------------------------------------------------------
    // Auction Cards
    //
    // card_type  = deck name: 'morning' or 'afternoon'
    // card_type_arg = index into AUCTION_CARD_DEFS
    //
    // Each def: 'peppers' => [color => count, ...]  (peppers received)
    // TODO_VERIFY: replace with exact publisher card list.
    // -------------------------------------------------------------------------

    const AUCTION_CARD_DEFS = [
        // Morning deck (indices 0-29, 30 cards)
        0  => ['deck' => 'morning', 'peppers' => ['red' => 1]],
        1  => ['deck' => 'morning', 'peppers' => ['red' => 1]],
        2  => ['deck' => 'morning', 'peppers' => ['red' => 1]],
        3  => ['deck' => 'morning', 'peppers' => ['yellow' => 1]],
        4  => ['deck' => 'morning', 'peppers' => ['yellow' => 1]],
        5  => ['deck' => 'morning', 'peppers' => ['yellow' => 1]],
        6  => ['deck' => 'morning', 'peppers' => ['blue' => 1]],
        7  => ['deck' => 'morning', 'peppers' => ['blue' => 1]],
        8  => ['deck' => 'morning', 'peppers' => ['blue' => 1]],
        9  => ['deck' => 'morning', 'peppers' => ['red' => 1, 'yellow' => 1]],
        10 => ['deck' => 'morning', 'peppers' => ['red' => 1, 'yellow' => 1]],
        11 => ['deck' => 'morning', 'peppers' => ['red' => 1, 'blue' => 1]],
        12 => ['deck' => 'morning', 'peppers' => ['red' => 1, 'blue' => 1]],
        13 => ['deck' => 'morning', 'peppers' => ['yellow' => 1, 'blue' => 1]],
        14 => ['deck' => 'morning', 'peppers' => ['yellow' => 1, 'blue' => 1]],
        15 => ['deck' => 'morning', 'peppers' => ['orange' => 1]],
        16 => ['deck' => 'morning', 'peppers' => ['orange' => 1]],
        17 => ['deck' => 'morning', 'peppers' => ['green' => 1]],
        18 => ['deck' => 'morning', 'peppers' => ['green' => 1]],
        19 => ['deck' => 'morning', 'peppers' => ['purple' => 1]],
        20 => ['deck' => 'morning', 'peppers' => ['purple' => 1]],
        21 => ['deck' => 'morning', 'peppers' => ['red' => 2]],
        22 => ['deck' => 'morning', 'peppers' => ['yellow' => 2]],
        23 => ['deck' => 'morning', 'peppers' => ['blue' => 2]],
        24 => ['deck' => 'morning', 'peppers' => ['orange' => 1, 'yellow' => 1]],
        25 => ['deck' => 'morning', 'peppers' => ['purple' => 1, 'red' => 1]],
        26 => ['deck' => 'morning', 'peppers' => ['green' => 1, 'blue' => 1]],
        27 => ['deck' => 'morning', 'peppers' => ['orange' => 1, 'green' => 1]],
        28 => ['deck' => 'morning', 'peppers' => ['purple' => 1, 'orange' => 1]],
        29 => ['deck' => 'morning', 'peppers' => ['white' => 1]],

        // Afternoon deck (indices 30-64, 35 cards)
        30 => ['deck' => 'afternoon', 'peppers' => ['orange' => 2]],
        31 => ['deck' => 'afternoon', 'peppers' => ['orange' => 2]],
        32 => ['deck' => 'afternoon', 'peppers' => ['green' => 2]],
        33 => ['deck' => 'afternoon', 'peppers' => ['green' => 2]],
        34 => ['deck' => 'afternoon', 'peppers' => ['purple' => 2]],
        35 => ['deck' => 'afternoon', 'peppers' => ['purple' => 2]],
        36 => ['deck' => 'afternoon', 'peppers' => ['white' => 1]],
        37 => ['deck' => 'afternoon', 'peppers' => ['white' => 1]],
        38 => ['deck' => 'afternoon', 'peppers' => ['white' => 1]],
        39 => ['deck' => 'afternoon', 'peppers' => ['white' => 2]],
        40 => ['deck' => 'afternoon', 'peppers' => ['brown' => 1]],
        41 => ['deck' => 'afternoon', 'peppers' => ['brown' => 1]],
        42 => ['deck' => 'afternoon', 'peppers' => ['black' => 1]],
        43 => ['deck' => 'afternoon', 'peppers' => ['black' => 1]],
        44 => ['deck' => 'afternoon', 'peppers' => ['black' => 1]],
        45 => ['deck' => 'afternoon', 'peppers' => ['orange' => 1, 'purple' => 1]],
        46 => ['deck' => 'afternoon', 'peppers' => ['orange' => 1, 'green' => 1]],
        47 => ['deck' => 'afternoon', 'peppers' => ['purple' => 1, 'green' => 1]],
        48 => ['deck' => 'afternoon', 'peppers' => ['white' => 1, 'orange' => 1]],
        49 => ['deck' => 'afternoon', 'peppers' => ['white' => 1, 'purple' => 1]],
        50 => ['deck' => 'afternoon', 'peppers' => ['white' => 1, 'green' => 1]],
        51 => ['deck' => 'afternoon', 'peppers' => ['black' => 1, 'red' => 1]],
        52 => ['deck' => 'afternoon', 'peppers' => ['black' => 1, 'yellow' => 1]],
        53 => ['deck' => 'afternoon', 'peppers' => ['black' => 1, 'blue' => 1]],
        54 => ['deck' => 'afternoon', 'peppers' => ['black' => 1, 'orange' => 1]],
        55 => ['deck' => 'afternoon', 'peppers' => ['brown' => 2]],
        56 => ['deck' => 'afternoon', 'peppers' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        57 => ['deck' => 'afternoon', 'peppers' => ['orange' => 1, 'green' => 1, 'purple' => 1]],
        58 => ['deck' => 'afternoon', 'peppers' => ['white' => 1, 'brown' => 1]],
        59 => ['deck' => 'afternoon', 'peppers' => ['black' => 2]],
        60 => ['deck' => 'afternoon', 'peppers' => ['black' => 1, 'white' => 1]],
        61 => ['deck' => 'afternoon', 'peppers' => ['orange' => 3]],
        62 => ['deck' => 'afternoon', 'peppers' => ['green' => 3]],
        63 => ['deck' => 'afternoon', 'peppers' => ['purple' => 3]],
        64 => ['deck' => 'afternoon', 'peppers' => ['white' => 3]],
    ];

    // -------------------------------------------------------------------------
    // Market Cards
    //
    // card_type  = deck name: 'morning' or 'afternoon'
    // card_type_arg = index into MARKET_CARD_DEFS
    //
    // Each def:
    //   'cost'    => [color => count]  (peppers to pay)
    //   'coins'   => int               (coins received)
    //   'peppers' => [color => count]  (peppers received)
    //   'points'  => int               (VP on the kept card)
    // TODO_VERIFY: replace with exact publisher card list.
    // -------------------------------------------------------------------------

    const MARKET_CARD_DEFS = [
        // Morning (indices 0-23, 24 cards)
        0  => ['deck'=>'morning','cost'=>['red'=>1],'coins'=>3,'peppers'=>[],'points'=>1],
        1  => ['deck'=>'morning','cost'=>['yellow'=>1],'coins'=>3,'peppers'=>[],'points'=>1],
        2  => ['deck'=>'morning','cost'=>['blue'=>1],'coins'=>3,'peppers'=>[],'points'=>1],
        3  => ['deck'=>'morning','cost'=>['red'=>1,'yellow'=>1],'coins'=>2,'peppers'=>['orange'=>1],'points'=>1],
        4  => ['deck'=>'morning','cost'=>['red'=>1,'blue'=>1],'coins'=>2,'peppers'=>['purple'=>1],'points'=>1],
        5  => ['deck'=>'morning','cost'=>['yellow'=>1,'blue'=>1],'coins'=>2,'peppers'=>['green'=>1],'points'=>1],
        6  => ['deck'=>'morning','cost'=>['red'=>2],'coins'=>4,'peppers'=>[],'points'=>2],
        7  => ['deck'=>'morning','cost'=>['yellow'=>2],'coins'=>4,'peppers'=>[],'points'=>2],
        8  => ['deck'=>'morning','cost'=>['blue'=>2],'coins'=>4,'peppers'=>[],'points'=>2],
        9  => ['deck'=>'morning','cost'=>['orange'=>1],'coins'=>3,'peppers'=>['yellow'=>1],'points'=>1],
        10 => ['deck'=>'morning','cost'=>['green'=>1],'coins'=>3,'peppers'=>['blue'=>1],'points'=>1],
        11 => ['deck'=>'morning','cost'=>['purple'=>1],'coins'=>3,'peppers'=>['red'=>1],'points'=>1],
        12 => ['deck'=>'morning','cost'=>['red'=>1,'orange'=>1],'coins'=>2,'peppers'=>['brown'=>1],'points'=>2],
        13 => ['deck'=>'morning','cost'=>['yellow'=>1,'orange'=>1],'coins'=>3,'peppers'=>[],'points'=>2],
        14 => ['deck'=>'morning','cost'=>['blue'=>1,'purple'=>1],'coins'=>3,'peppers'=>[],'points'=>2],
        15 => ['deck'=>'morning','cost'=>['orange'=>1,'green'=>1],'coins'=>1,'peppers'=>['white'=>1],'points'=>2],
        16 => ['deck'=>'morning','cost'=>['orange'=>1,'purple'=>1],'coins'=>1,'peppers'=>['white'=>1],'points'=>2],
        17 => ['deck'=>'morning','cost'=>['green'=>1,'purple'=>1],'coins'=>1,'peppers'=>['white'=>1],'points'=>2],
        18 => ['deck'=>'morning','cost'=>['orange'=>2],'coins'=>0,'peppers'=>['black'=>1],'points'=>3],
        19 => ['deck'=>'morning','cost'=>['red'=>1,'yellow'=>1,'blue'=>1],'coins'=>5,'peppers'=>[],'points'=>2],
        20 => ['deck'=>'morning','cost'=>['orange'=>1,'green'=>1,'purple'=>1],'coins'=>4,'peppers'=>[],'points'=>3],
        21 => ['deck'=>'morning','cost'=>['red'=>2,'yellow'=>2],'coins'=>6,'peppers'=>[],'points'=>3],
        22 => ['deck'=>'morning','cost'=>['blue'=>2,'purple'=>2],'coins'=>6,'peppers'=>[],'points'=>3],
        23 => ['deck'=>'morning','cost'=>['green'=>2,'orange'=>2],'coins'=>6,'peppers'=>[],'points'=>3],

        // Afternoon (indices 24-47, 24 cards)
        24 => ['deck'=>'afternoon','cost'=>['white'=>1],'coins'=>5,'peppers'=>[],'points'=>2],
        25 => ['deck'=>'afternoon','cost'=>['brown'=>1],'coins'=>4,'peppers'=>['orange'=>1],'points'=>2],
        26 => ['deck'=>'afternoon','cost'=>['black'=>1],'coins'=>7,'peppers'=>[],'points'=>3],
        27 => ['deck'=>'afternoon','cost'=>['white'=>1,'orange'=>1],'coins'=>4,'peppers'=>[],'points'=>4],
        28 => ['deck'=>'afternoon','cost'=>['white'=>1,'purple'=>1],'coins'=>4,'peppers'=>[],'points'=>4],
        29 => ['deck'=>'afternoon','cost'=>['white'=>1,'green'=>1],'coins'=>4,'peppers'=>[],'points'=>4],
        30 => ['deck'=>'afternoon','cost'=>['black'=>1,'red'=>1],'coins'=>5,'peppers'=>[],'points'=>4],
        31 => ['deck'=>'afternoon','cost'=>['black'=>1,'yellow'=>1],'coins'=>5,'peppers'=>[],'points'=>4],
        32 => ['deck'=>'afternoon','cost'=>['black'=>1,'blue'=>1],'coins'=>5,'peppers'=>[],'points'=>4],
        33 => ['deck'=>'afternoon','cost'=>['white'=>2],'coins'=>6,'peppers'=>[],'points'=>5],
        34 => ['deck'=>'afternoon','cost'=>['black'=>2],'coins'=>8,'peppers'=>[],'points'=>6],
        35 => ['deck'=>'afternoon','cost'=>['white'=>1,'black'=>1],'coins'=>6,'peppers'=>[],'points'=>6],
        36 => ['deck'=>'afternoon','cost'=>['orange'=>2,'green'=>2],'coins'=>7,'peppers'=>[],'points'=>4],
        37 => ['deck'=>'afternoon','cost'=>['orange'=>2,'purple'=>2],'coins'=>7,'peppers'=>[],'points'=>4],
        38 => ['deck'=>'afternoon','cost'=>['green'=>2,'purple'=>2],'coins'=>7,'peppers'=>[],'points'=>4],
        39 => ['deck'=>'afternoon','cost'=>['white'=>1,'orange'=>1,'green'=>1],'coins'=>5,'peppers'=>[],'points'=>5],
        40 => ['deck'=>'afternoon','cost'=>['brown'=>1,'white'=>1],'coins'=>4,'peppers'=>['black'=>1],'points'=>3],
        41 => ['deck'=>'afternoon','cost'=>['black'=>1,'orange'=>1],'coins'=>6,'peppers'=>[],'points'=>5],
        42 => ['deck'=>'afternoon','cost'=>['black'=>1,'white'=>1],'coins'=>7,'peppers'=>[],'points'=>6],
        43 => ['deck'=>'afternoon','cost'=>['red'=>3,'yellow'=>3,'blue'=>3],'coins'=>10,'peppers'=>[],'points'=>5],
        44 => ['deck'=>'afternoon','cost'=>['orange'=>3,'green'=>3,'purple'=>3],'coins'=>10,'peppers'=>[],'points'=>6],
        45 => ['deck'=>'afternoon','cost'=>['white'=>3],'coins'=>9,'peppers'=>[],'points'=>7],
        46 => ['deck'=>'afternoon','cost'=>['black'=>3],'coins'=>12,'peppers'=>[],'points'=>9],
        47 => ['deck'=>'afternoon','cost'=>['white'=>2,'black'=>2],'coins'=>10,'peppers'=>[],'points'=>10],
    ];

    // -------------------------------------------------------------------------
    // Recipe Cards (Chili Cookoff)
    //
    // card_type_arg = index into RECIPE_CARD_DEFS
    //
    // Each def:
    //   'cost'   => [color => count]
    //   'points' => int
    // TODO_VERIFY: replace with exact publisher card list.
    // -------------------------------------------------------------------------

    const RECIPE_CARD_DEFS = [
        0  => ['cost'=>['red'=>2,'yellow'=>2],'points'=>4],
        1  => ['cost'=>['red'=>2,'blue'=>2],'points'=>4],
        2  => ['cost'=>['yellow'=>2,'blue'=>2],'points'=>4],
        3  => ['cost'=>['red'=>1,'yellow'=>1,'blue'=>1],'points'=>3],
        4  => ['cost'=>['orange'=>2,'red'=>1],'points'=>5],
        5  => ['cost'=>['green'=>2,'blue'=>1],'points'=>5],
        6  => ['cost'=>['purple'=>2,'red'=>1],'points'=>5],
        7  => ['cost'=>['orange'=>1,'green'=>1,'purple'=>1],'points'=>6],
        8  => ['cost'=>['orange'=>2,'green'=>2],'points'=>7],
        9  => ['cost'=>['orange'=>2,'purple'=>2],'points'=>7],
        10 => ['cost'=>['green'=>2,'purple'=>2],'points'=>7],
        11 => ['cost'=>['white'=>1,'orange'=>1],'points'=>6],
        12 => ['cost'=>['white'=>1,'green'=>1],'points'=>6],
        13 => ['cost'=>['white'=>1,'purple'=>1],'points'=>6],
        14 => ['cost'=>['white'=>2,'red'=>2],'points'=>8],
        15 => ['cost'=>['white'=>1,'brown'=>1,'orange'=>1],'points'=>7],
        16 => ['cost'=>['black'=>1,'orange'=>2],'points'=>9],
        17 => ['cost'=>['black'=>1,'green'=>2],'points'=>9],
        18 => ['cost'=>['black'=>1,'purple'=>2],'points'=>9],
        19 => ['cost'=>['black'=>1,'white'=>1],'points'=>10],
        20 => ['cost'=>['black'=>2,'red'=>2],'points'=>11],
        21 => ['cost'=>['black'=>2,'yellow'=>2],'points'=>11],
        22 => ['cost'=>['black'=>2,'blue'=>2],'points'=>11],
        23 => ['cost'=>['black'=>2,'brown'=>2],'points'=>12],
        24 => ['cost'=>['black'=>2,'white'=>2],'points'=>14],
        25 => ['cost'=>['black'=>3,'orange'=>2],'points'=>15],
        26 => ['cost'=>['black'=>3,'green'=>2],'points'=>15],
        27 => ['cost'=>['black'=>3,'purple'=>2],'points'=>15],
        28 => ['cost'=>['black'=>2,'brown'=>2,'red'=>2],'points'=>16],
        29 => ['cost'=>['black'=>3,'white'=>3],'points'=>18],
    ];

    // Convenience: look up pepper plaque category for a given planted colour
    public static function plaqueCategoryForPepper(string $color): ?string
    {
        if (in_array($color, self::STRIPED_PEPPERS)) return 'striped';
        if (in_array($color, ['red', 'yellow', 'blue', 'white', 'black'])) return $color;
        return null; // brown has no plaque
    }
}
