import { type ClassValue, clsx } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
	return twMerge(clsx(inputs));
}

/**
 * Currency table — mirrors App\Payments\Domain\Enums\Currency.
 * Compile-time only; refreshed manually when the PHP enum adds a case.
 * Each entry: { symbol, exponent } where exponent is the number of
 * decimal digits in the minor unit (0 for JPY, 2 for everything else).
 */
const CURRENCIES: Record<string, { symbol: string; exponent: number }> = {
	INR: { symbol: "₹", exponent: 2 },
	USD: { symbol: "$", exponent: 2 },
	EUR: { symbol: "€", exponent: 2 },
	GBP: { symbol: "£", exponent: 2 },
	AUD: { symbol: "A$", exponent: 2 },
	CAD: { symbol: "C$", exponent: 2 },
	SGD: { symbol: "S$", exponent: 2 },
	AED: { symbol: "د.إ", exponent: 2 },
	JPY: { symbol: "¥", exponent: 0 },
};

/**
 * Format a minor-unit amount into its major-unit display string with the
 * currency symbol prefixed. e.g. (1250000, 'INR') -> '₹12,500.00'.
 * Returns '—' when the currency code is unknown so callers never
 * throw on a DTO we haven't seen before.
 */
export function formatMoney(
	amountMinor: number | null | undefined,
	currencyCode: string | null | undefined,
): string {
	if (amountMinor === null || amountMinor === undefined) return "—";
	const ccy = CURRENCIES[currencyCode ?? ""];
	if (!ccy) return `${amountMinor} ${currencyCode ?? ""}`.trim();
	const major = amountMinor / 10 ** ccy.exponent;
	const formatted = major.toLocaleString("en-US", {
		minimumFractionDigits: ccy.exponent,
		maximumFractionDigits: ccy.exponent,
	});
	return `${ccy.symbol}${formatted}`;
}

/**
 * Compute the percentage of (raised / target). Returns 0 when target is
 * null/0 to avoid division-by-zero. Capped to 100 for display purposes
 * but the bar component is welcome to render overflow.
 */
export function percentOf(raised: number, target: number | null | undefined): number {
	if (target === null || target === undefined || target <= 0) return 0;
	return Math.max(0, Math.min(100, Math.round((raised / target) * 100)));
}

/**
 * Mirror of App\Payments\Domain\ValueObjects\Money. The wire shape
 * remains (amount_minor: int, currency_code: string) — this is a
 * structural type for components that handle money objects explicitly,
 * not a replacement for the existing `formatMoney(amountMinor, currencyCode)`
 * call sites. See PR 6 step 3 of the plan.
 */
export interface Money {
	amount_minor: number;
	currency_code: string;
}

export function formatMoneyObject(m: Money): string {
	return formatMoney(m.amount_minor, m.currency_code);
}
