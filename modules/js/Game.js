/**
 * Scoville — BGA client-side logic
 */

// ============================================================================
// State: AuctionBid (simultaneous bidding)
// ============================================================================

class AuctionBid {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('Secretly choose a bid for turn order'));
            const myCoins = args.player_coins[this.bga.players.getMyPlayerId()]?.coins ?? 0;
            // Render a coin-selector UI; for now add quick buttons 0–myCoins
            for (let amt = 0; amt <= Math.min(myCoins, 10); amt++) {
                this.bga.statusBar.addActionButton(`$${amt}`, () => this.onBid(amt));
            }
        } else {
            this.bga.statusBar.setTitle(_('Waiting for all players to bid…'));
        }
    }
    onLeavingState() {}

    onBid(amount) {
        this.bga.actions.performAction('actBid', { bid_amount: amount });
    }
}

// ============================================================================
// State: TurnOrderChoice
// ============================================================================

class TurnOrderChoice {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('Choose your turn order slot'));
            for (const slot of args.available_slots) {
                this.bga.statusBar.addActionButton(
                    _('Slot ${n}').replace('${n}', slot),
                    () => this.bga.actions.performAction('actChooseSlot', { slot })
                );
            }
        } else {
            this.bga.statusBar.setTitle(_('${actplayer} is choosing a turn order slot'));
        }
    }
    onLeavingState() {}
}

// ============================================================================
// State: AuctionClaim
// ============================================================================

class AuctionClaim {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} must claim an Auction card'));
            // Cards are highlighted client-side; clicking calls onCardClick
            this.game.highlightAuctionCards(args.auction_display, true);
        } else {
            this.bga.statusBar.setTitle(_('${actplayer} is claiming an Auction card'));
        }
    }
    onLeavingState(args, isCurrentPlayerActive) {
        this.game.highlightAuctionCards([], false);
    }
}

// ============================================================================
// State: Planting
// ============================================================================

class Planting {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} must plant a pepper'));
            this.game.highlightValidPlots(args.valid_plots, true);
            if (args.planted_once && this.game.myBonusTiles['extra_plant']) {
                this.bga.statusBar.addActionButton(
                    _('Skip second plant'), () => this.bga.actions.performAction('actSkipBonusPlant'),
                    { color: 'secondary' }
                );
            }
        } else {
            this.bga.statusBar.setTitle(_('${actplayer} is planting a pepper'));
        }
    }
    onLeavingState() { this.game.highlightValidPlots([], false); }
}

// ============================================================================
// State: Harvesting
// ============================================================================

class Harvesting {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(
                _('${you} must move your farmer (${n}/${max} steps)')
                    .replace('${n}', args.steps_taken)
                    .replace('${max}', args.max_steps)
            );
            this.game.highlightValidNotches(args.valid_notches, true);

            if (args.can_stop) {
                this.bga.statusBar.addActionButton(
                    _('Stop moving'), () => this.bga.actions.performAction('actEndHarvest'),
                    { color: 'secondary' }
                );
            }
            if (args.steps_taken === 3 && this.game.myBonusTiles['extra_step']) {
                this.bga.statusBar.addActionButton(
                    _('Use Extra Step tile'),
                    () => this.bga.actions.performAction('actUseExtraStep')
                );
            }
        } else {
            this.bga.statusBar.setTitle(_('${actplayer} is moving their farmer'));
        }
    }
    onLeavingState() { this.game.highlightValidNotches([], false); }
}

// ============================================================================
// State: Fulfillment
// ============================================================================

class Fulfillment {
    constructor(game, bga) { this.game = game; this.bga = bga; }

    onEnteringState(args, isCurrentPlayerActive) {
        if (isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${you} may fulfill cards and sell peppers'));

            if (args.can_market) {
                this.game.highlightMarketCards(args.market_display, true);
            }
            if (args.can_recipe) {
                this.game.highlightRecipeCards(args.recipe_display, true);
            }
            if (args.can_sell) {
                this.bga.statusBar.addActionButton(
                    _('Sell peppers'), () => this.game.openSellDialog()
                );
            }

            this.bga.statusBar.addActionButton(
                _('Pass'), () => this.bga.actions.performAction('actEndFulfillment'),
                { color: 'secondary' }
            );
        } else {
            this.bga.statusBar.setTitle(_('${actplayer} is in the Fulfillment phase'));
        }
    }
    onLeavingState() {
        this.game.highlightMarketCards([], false);
        this.game.highlightRecipeCards([], false);
    }
}

// ============================================================================
// Main Game class
// ============================================================================

export class Game {
    constructor(bga) {
        this.bga = bga;

        // Register state handlers
        this.bga.states.register('AuctionBid',      new AuctionBid(this, bga));
        this.bga.states.register('AuctionResolve',  null); // server-only GAME state
        this.bga.states.register('TurnOrderChoice', new TurnOrderChoice(this, bga));
        this.bga.states.register('AuctionClaim',    new AuctionClaim(this, bga));
        this.bga.states.register('Planting',        new Planting(this, bga));
        this.bga.states.register('Harvesting',      new Harvesting(this, bga));
        this.bga.states.register('Fulfillment',     new Fulfillment(this, bga));

        // Runtime data populated in setup()
        this.pepperField  = {};   // key "r_c" → color
        this.myBonusTiles = {};   // type → tile object
        this.myPeppers    = {};   // color → count
    }

    // -------------------------------------------------------------------------
    // setup
    // -------------------------------------------------------------------------

    setup(gamedatas) {
        this.gamedatas = gamedatas;

        // Build game area scaffold
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="scoville-wrap">
                <div id="pepper-field"></div>
                <div id="card-areas">
                    <div id="auction-display"></div>
                    <div id="market-display"></div>
                    <div id="recipe-display"></div>
                </div>
            </div>
        `);

        // Pepper field
        this.pepperField = {};
        for (const [key, plot] of Object.entries(gamedatas.pepper_field)) {
            this.pepperField[key] = plot.color;
        }
        this.renderPepperField(gamedatas.pepper_field);

        // Player panels
        for (const [pid, player] of Object.entries(gamedatas.players)) {
            this.bga.playerPanels.getElement(Number(pid)).insertAdjacentHTML('beforeend', `
                <div class="scov-panel-coins">
                    💰 <span id="coins-${pid}">${player.coins}</span>
                </div>
                <div class="scov-panel-order">
                    Turn order: <span id="order-${pid}">${player.turn_order}</span>
                </div>
            `);
        }

        // My resources
        this.myPeppers = {};
        for (const [color, row] of Object.entries(gamedatas.my_peppers)) {
            this.myPeppers[color] = Number(row.count);
        }

        this.myBonusTiles = {};
        for (const [id, tile] of Object.entries(gamedatas.my_bonus_tiles)) {
            if (!tile.used) this.myBonusTiles[tile.tile_type] = tile;
        }

        // Cards
        this.renderAuctionDisplay(gamedatas.auction_display);
        this.renderMarketDisplay(gamedatas.market_display);
        this.renderRecipeDisplay(gamedatas.recipe_display);

        this.setupNotifications();
    }

    // -------------------------------------------------------------------------
    // Rendering helpers (stubs — replace with real art/CSS later)
    // -------------------------------------------------------------------------

    renderPepperField(plots) {
        const field = document.getElementById('pepper-field');
        field.innerHTML = '';
        for (const plot of Object.values(plots)) {
            const el = document.createElement('div');
            el.className = `scov-plot scov-pepper-${plot.color}`;
            el.dataset.row = plot.row;
            el.dataset.col = plot.col;
            el.id = `plot-${plot.row}-${plot.col}`;
            field.appendChild(el);
        }
    }

    renderAuctionDisplay(cards) {
        const area = document.getElementById('auction-display');
        area.innerHTML = '<h3>Auction House</h3>';
        for (const card of cards) {
            area.insertAdjacentHTML('beforeend',
                `<div class="scov-card scov-auction-card" id="auction-card-${card.id}"
                      data-id="${card.id}">Card ${card.id}</div>`);
        }
    }

    renderMarketDisplay(cards) {
        const area = document.getElementById('market-display');
        area.innerHTML = "<h3>Farmers' Market</h3>";
        for (const card of cards) {
            area.insertAdjacentHTML('beforeend',
                `<div class="scov-card scov-market-card" id="market-card-${card.id}"
                      data-id="${card.id}">Market ${card.id}</div>`);
        }
    }

    renderRecipeDisplay(cards) {
        const area = document.getElementById('recipe-display');
        area.innerHTML = '<h3>Chili Cookoff</h3>';
        for (const card of cards) {
            area.insertAdjacentHTML('beforeend',
                `<div class="scov-card scov-recipe-card" id="recipe-card-${card.id}"
                      data-id="${card.id}">Recipe ${card.id}</div>`);
        }
    }

    // -------------------------------------------------------------------------
    // Interaction helpers called by state handlers
    // -------------------------------------------------------------------------

    highlightAuctionCards(cards, on) {
        document.querySelectorAll('.scov-auction-card').forEach(el => {
            el.classList.toggle('scov-selectable', false);
            el.onclick = null;
        });
        if (!on) return;
        for (const card of cards) {
            const el = document.getElementById(`auction-card-${card.id}`);
            if (!el) continue;
            el.classList.add('scov-selectable');
            el.onclick = () => this.bga.actions.performAction(
                'actClaimAuctionCard', { card_id: Number(card.id) }
            );
        }
    }

    highlightValidPlots(plots, on) {
        document.querySelectorAll('.scov-plot-target').forEach(el => {
            el.remove();
        });
        if (!on) return;
        // TODO: render clickable plot targets on the field grid
        // For each plot in plots, overlay a click target element
    }

    highlightValidNotches(notchIds, on) {
        document.querySelectorAll('.scov-notch-target').forEach(el => {
            el.remove();
        });
        if (!on) return;
        // TODO: render clickable notch targets on the field
    }

    highlightMarketCards(cards, on) {
        document.querySelectorAll('.scov-market-card').forEach(el => {
            el.classList.toggle('scov-selectable', false);
            el.onclick = null;
        });
        if (!on) return;
        for (const card of cards) {
            const el = document.getElementById(`market-card-${card.id}`);
            if (!el) continue;
            el.classList.add('scov-selectable');
            el.onclick = () => this.bga.actions.performAction(
                'actFulfillMarket', { card_id: Number(card.id) }
            );
        }
    }

    highlightRecipeCards(cards, on) {
        document.querySelectorAll('.scov-recipe-card').forEach(el => {
            el.classList.toggle('scov-selectable', false);
            el.onclick = null;
        });
        if (!on) return;
        for (const card of cards) {
            const el = document.getElementById(`recipe-card-${card.id}`);
            if (!el) continue;
            el.classList.add('scov-selectable');
            el.onclick = () => this.bga.actions.performAction(
                'actFulfillRecipe', { card_id: Number(card.id) }
            );
        }
    }

    openSellDialog() {
        // TODO: show a dialog letting the player choose a colour and count
        // For now, a basic prompt
        const color = window.prompt('Sell which pepper colour? (red/yellow/blue/orange/green/purple/white/brown/black)');
        if (!color) return;
        const count = parseInt(window.prompt('How many? (1–5)') ?? '0', 10);
        if (!count || count < 1 || count > 5) return;
        this.bga.actions.performAction('actSellPeppers', { color, count });
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    setupNotifications() {
        this.bga.notifications.setupPromiseNotifications();
    }

    async notif_pepperPlanted(args) {
        const key = `${args.row}_${args.col}`;
        this.pepperField[key] = args.color;
        const field = document.getElementById('pepper-field');
        let el = document.getElementById(`plot-${args.row}-${args.col}`);
        if (!el) {
            el = document.createElement('div');
            el.id    = `plot-${args.row}-${args.col}`;
            el.dataset.row = args.row;
            el.dataset.col = args.col;
            field.appendChild(el);
        }
        el.className = `scov-plot scov-pepper-${args.color}`;
    }

    async notif_auctionCardClaimed(args) {
        const el = document.getElementById(`auction-card-${args.card_id}`);
        el?.remove();
    }

    async notif_marketFulfilled(args) {
        const el = document.getElementById(`market-card-${args.card_id}`);
        el?.remove();
        // Update coin display for the player
        const coinEl = document.getElementById(`coins-${args.player_id}`);
        if (coinEl) coinEl.textContent = Number(coinEl.textContent) + args.coins_gained;
    }

    async notif_recipeFulfilled(args) {
        const el = document.getElementById(`recipe-card-${args.card_id}`);
        el?.remove();
    }

    async notif_peppersSold(args) {
        const coinEl = document.getElementById(`coins-${args.player_id}`);
        if (coinEl) coinEl.textContent = Number(coinEl.textContent) + args.earned;
    }

    async notif_farmerMoved(args) {
        // TODO: animate the farmer pawn to the new notch position
    }

    async notif_bidsRevealed(args) {
        // TODO: show bid reveal animation
    }

    async notif_turnOrderChosen(args) {
        const el = document.getElementById(`order-${args.player_id}`);
        if (el) el.textContent = args.slot;
    }

    async notif_plaqueAwarded(args) {
        // TODO: animate plaque from City Hall to player panel
    }

    async notif_bonusTileUsed(args) {
        delete this.myBonusTiles[args.tile_type];
    }

    async notif_newRound(args) {
        // TODO: visual round indicator
    }

    async notif_afternoonBegins() {
        // TODO: visual transition
    }

    async notif_finalRound() {
        this.bga.statusBar.setTitle(_('Final round!'));
    }

    async notif_finalScore(args) {
        // BGA framework handles score display automatically
    }

    async notif_fulfillmentPassed() {}
}
