-- =====================================================================
-- Reference / seed data
-- Idempotent: safe to re-run
-- =====================================================================

-- ---------------------------------------------------------------------
-- Currencies  (subset matching PaymentGatewayContract::Currency)
-- ---------------------------------------------------------------------

INSERT INTO currencies (code, name, symbol, minor_unit_digits, display_order) VALUES
    ('INR', 'Indian Rupee',           '₹',  2, 10),
    ('USD', 'United States Dollar',   '$',  2, 20),
    ('EUR', 'Euro',                   '€',  2, 30),
    ('GBP', 'Pound Sterling',         '£',  2, 40),
    ('AUD', 'Australian Dollar',      'A$', 2, 50),
    ('CAD', 'Canadian Dollar',        'C$', 2, 60),
    ('SGD', 'Singapore Dollar',       'S$', 2, 70),
    ('AED', 'UAE Dirham',             'د.إ', 2, 80),
    ('JPY', 'Japanese Yen',           '¥',  0, 90)
ON CONFLICT (code) DO UPDATE SET
    name              = EXCLUDED.name,
    symbol            = EXCLUDED.symbol,
    minor_unit_digits = EXCLUDED.minor_unit_digits,
    display_order     = EXCLUDED.display_order,
    updated_at        = NOW();

-- ---------------------------------------------------------------------
-- Payment providers  (priority: lower number = preferred)
-- ---------------------------------------------------------------------

INSERT INTO payment_providers (code, display_name, is_active, priority, supported_currencies, min_amount_minor, max_amount_minor) VALUES
    ('razorpay',  'Razorpay',  TRUE, 10, ARRAY['INR']::CHAR(3)[],          100,    1000000000),
    ('paypal',    'PayPal',    TRUE, 20, ARRAY['USD','EUR','GBP','AUD','CAD','SGD','AED','JPY']::CHAR(3)[], 100, 100000000),
    ('cashfree',  'Cashfree',  FALSE, 30, ARRAY['INR']::CHAR(3)[],          100,    500000000),
    ('binance',   'Binance Pay',         FALSE, 40, ARRAY['USD','EUR','GBP']::CHAR(3)[], 100, 1000000000),
    ('coinbase',  'Coinbase Commerce',   FALSE, 50, ARRAY['USD','EUR','GBP']::CHAR(3)[], 100, 1000000000),
    ('nowpayments','NOWPayments',       FALSE, 60, ARRAY['USD','EUR']::CHAR(3)[],         100, 1000000000),
    ('triplea',   'TripleA',             FALSE, 70, ARRAY['USD','SGD','JPY']::CHAR(3)[], 100, 1000000000)
ON CONFLICT (code) DO UPDATE SET
    display_name         = EXCLUDED.display_name,
    is_active            = EXCLUDED.is_active,
    priority             = EXCLUDED.priority,
    supported_currencies = EXCLUDED.supported_currencies,
    min_amount_minor     = EXCLUDED.min_amount_minor,
    max_amount_minor     = EXCLUDED.max_amount_minor,
    updated_at           = NOW();
