
-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- Scoville implementation
-- -----

-- Extend the standard player table with Scoville-specific fields.
ALTER TABLE `player`
    ADD `player_coins`         INT UNSIGNED NOT NULL DEFAULT 10,
    ADD `player_bid`           INT UNSIGNED NOT NULL DEFAULT 0,
    ADD `player_bid_done`      TINYINT(1)   NOT NULL DEFAULT 0,
    ADD `player_turn_order`    INT UNSIGNED NOT NULL DEFAULT 0,
    ADD `farmer_notch`         INT          NOT NULL DEFAULT -1; -- -1 = star

-- ----------------------------------------------------------------------------
-- Pepper inventory: how many of each colour each player holds behind the screen
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `player_pepper` (
    `player_id`    INT UNSIGNED NOT NULL,
    `pepper_color` VARCHAR(16)  NOT NULL,
    `count`        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`player_id`, `pepper_color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Pepper field: which colour is planted in each plot
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pepper_field` (
    `plot_row`     INT          NOT NULL,
    `plot_col`     INT          NOT NULL,
    `pepper_color` VARCHAR(16)  NOT NULL,
    PRIMARY KEY (`plot_row`, `plot_col`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Auction cards  (BGA Deck component)
-- card_type     = 'morning' or 'afternoon'
-- card_type_arg = index into Material::AUCTION_CARD_DEFS
-- card_location = 'deck_morning' | 'deck_afternoon' | 'display' | 'discard'
--               | 'player'  (player_id in card_location_arg)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auction_card` (
    `card_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_type`         VARCHAR(16)  NOT NULL,
    `card_type_arg`     INT          NOT NULL,
    `card_location`     VARCHAR(32)  NOT NULL,
    `card_location_arg` INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- ----------------------------------------------------------------------------
-- Market cards  (BGA Deck component)
-- card_type     = 'morning' or 'afternoon'
-- card_type_arg = index into Material::MARKET_CARD_DEFS
-- card_location = 'deck_morning' | 'deck_afternoon' | 'display' | 'discard'
--               | 'player'  (player_id in card_location_arg)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `market_card` (
    `card_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_type`         VARCHAR(16)  NOT NULL,
    `card_type_arg`     INT          NOT NULL,
    `card_location`     VARCHAR(32)  NOT NULL,
    `card_location_arg` INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- ----------------------------------------------------------------------------
-- Recipe cards  (BGA Deck component)
-- card_type_arg = index into Material::RECIPE_CARD_DEFS
-- card_location = 'deck' | 'display' | 'discard' | 'player'
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recipe_card` (
    `card_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `card_type`         VARCHAR(16)  NOT NULL DEFAULT 'recipe',
    `card_type_arg`     INT          NOT NULL,
    `card_location`     VARCHAR(32)  NOT NULL,
    `card_location_arg` INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- ----------------------------------------------------------------------------
-- Award plaques: which plaques remain on City Hall vs. earned by a player
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `award_plaque` (
    `plaque_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pepper_color` VARCHAR(16)  NOT NULL,   -- category: red/yellow/blue/striped/white/black
    `points`       INT UNSIGNED NOT NULL,
    `player_id`    INT UNSIGNED,            -- NULL = still on City Hall
    PRIMARY KEY (`plaque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- ----------------------------------------------------------------------------
-- Bonus action tiles: 3 per player; tracked individually so we know if used
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bonus_tile` (
    `tile_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT UNSIGNED NOT NULL,
    `tile_type` VARCHAR(32)  NOT NULL,   -- extra_plant | extra_step | double_back
    `used`      TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`tile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;