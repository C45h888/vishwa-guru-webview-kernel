# Mail Kernel

> One-screen navigation map for the standalone `Mail` package.
> Read order: Boundaries → Contracts → Providers → FSMs → Tests.

## Boundaries

**Owns:** outbound mail as a service — the Hostinger Mail API (Agentic
Mail, `api.mail.hostinger.com` over HTTPS) boundary, the canonical email
composition logic, and the worker chain that turns a typed receipt
document into a delivered email. The package is headed by the mother
file `MailSubstrate`, which owns every Hostinger boundary; the
`MailDispatchCoordinator` is the seam that sits BETWEEN the payments
system and this package and is the only entry the payments system may
call.

**Does NOT own:** receipt facts (the typed `ReceiptDocument` is produced
by the payments side's predefined DB workers), delivery state (the
ReceiptStateMachine on the payments side), or the receipt PDF design
(the receipts design file system).

**Inbound edges (ports, implemented on the Payments side):**
- `App\Mail\Contracts\ReceiptSourceContract` →
  `App\Payments\Mail\ReceiptSourceAdapter` (delegates to the predefined
  DataWorker/TypesWorker via `ReceiptSubstrate::documentFor` + the stored
  PDF artifact). This port's `ReceiptDocument` return type is the
  package's ONE sanctioned typed dependency on the payments kernel — the
  same snapshot the receipt PDF renders.
- `App\Mail\Contracts\DeliveryBookkeepingContract` →
  `App\Payments\Mail\DeliveryBookkeepingAdapter` (ReceiptService
  `markDelivered` FSM bookkeeping + audit trail).

**Outbound edges:** Hostinger Mail API via
`App\Mail\Client\HostingerMailClient` (the ONLY construction site of
`hostinger/mail-api-php-sdk`; known namespace-collision caveat with
`hostinger/api-php-sdk` documented in the client).

## Contracts

| Contract | FQCN | Producer/Consumer |
|---|---|---|
| MailTransportContract | `App\Mail\Contracts\MailTransportContract` | Implemented by `MailSubstrate`; the provider seam (a future mail provider is one adapter) |
| ReceiptSourceContract | `App\Mail\Contracts\ReceiptSourceContract` | Port; implemented by `App\Payments\Mail\ReceiptSourceAdapter` |
| DeliveryBookkeepingContract | `App\Mail\Contracts\DeliveryBookkeepingContract` | Port; implemented by `App\Payments\Mail\DeliveryBookkeepingAdapter` |

## Providers

`App\Mail\Providers\MailServiceProvider` — pinned position: Queue →
Payments → **Mail** → Cms. Boots after Payments because the inbound
ports are bound on the Payments side.

Bindings: `HostingerMailClient` (from `config/hostinger-mail.php`, env
token only), `MailSubstrate` singleton (mother file),
`MailTransportContract → MailSubstrate`, the three workers
(`TransientTransportWorker`, `EmailMakingWorker`, `TransportWorker`),
and `MailDispatchCoordinator` (the seam).

## FSMs

None — the mail package mutates no state. Delivery state lives in the
payments kernel (`ReceiptStateMachine`, driven through
`DeliveryBookkeepingContract`; since 2026-10-03 durably persisted as
`receipts.delivery_status` — the `already_delivered` guard reads that
durable state, and `receipts:reconcile` re-dispatches `ReceiptEmailJob`
for failed/stale-pending rows); webhook event normalization
(`MailSubstrate::normalizeWebhookEvent`) is pure mapping, not a state
machine.

## Worker chain

`MailDispatchCoordinator::deliverReceipt(Receipt, signedUrl)` sequences:

1. `TransientTransportWorker` — PURE TRANSPORT: pulls the typed
   `ReceiptDocument` + donor data + the hash-true stored receipt PDF
   through `ReceiptSourceContract`.
2. `EmailMakingWorker` — THE EMAIL WRITER: composes subject/body via
   `MailSubstrate::composeReceiptEmail()` (the ONE canonical receipt
   email) and attaches the receipt PDF.
3. `TransportWorker` — ensures the email reaches the correct person:
   recipient validation + `MailSubstrate::send()`.

Work-boundary rules (enabled transport, recipient present, idempotency,
outcome bookkeeping) are enforced ONLY in the coordinator.

## Tests

- `tests/Unit/Mail/MailSubstrateTest.php` — boundaries, error taxonomy,
  webhook verification/normalization, canonical email composition + PII
  guard.
- `tests/Unit/Mail/Coordinator/MailDispatchCoordinatorTest.php` — the
  seam's boundary rules + worker sequencing + bookkeeping outcomes.
- `tests/Unit/Mail/Workers/*` — worker boundaries (composition,
  recipient validation, source delegation).
- `tests/Unit/Payments/Jobs/ReceiptEmailJobTest.php` — queue carrier
  delegation (idempotency keys, skip paths).
