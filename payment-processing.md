# PAYMENT_ARCHITECTURE.md

# Payment Processing Architecture

## Purpose

The Payment Processing Architecture defines the financial workflow of the Temple Trust Management System. This document establishes the principles, domain boundaries, processing lifecycle, verification strategy, failure handling, and operational responsibilities that govern every financial transaction processed by the application.

Financial integrity is considered one of the foundational architectural pillars of the system. Every component of the payment subsystem exists to ensure that donations are processed securely, verified deterministically, and persisted only after successful validation.

The objective of this architecture is not simply to integrate payment gateways, but to establish a predictable and auditable financial processing system that remains reliable under normal operation, partial failures, and future expansion.

---

# Architectural Philosophy

Payments are treated as an independent business domain.

A donation represents a user's intent to financially contribute toward the trust.

A payment represents the financial transaction used to fulfill that intent.

These are separate concepts with separate responsibilities.

The Donations domain manages donor intent, categorization, and business purpose.

The Payments domain manages gateway communication, transaction verification, settlement, and financial processing.

Maintaining this separation preserves clear domain boundaries and prevents payment infrastructure from becoming tightly coupled with donation business logic.

---

## Per-kernel Landing Page

> **Per-kernel landing page:** for a one-screen navigation map of the
> Payments kernel (Boundaries, Contracts, Providers, FSMs, Module class,
> Tests), see [`app/Payments/Kernel.md`](app/Payments/Kernel.md). Note that
> Payments HTTP controllers and form requests live inside the Payments
> kernel at `App\Payments\Http\Controllers\*` and
> `App\Payments\Http\Requests\*` — see
> [`agents.md` § Payments HTTP Consolidation](agents.md#payments-http-consolidation).

---

# Financial Integrity

Financial integrity is one of the core engineering principles of the repository.

No financial record becomes authoritative until the payment gateway has cryptographically verified the transaction through its official callback mechanism.

Client-side success responses are never considered proof of payment.

Database persistence occurs only after successful gateway verification.

Receipts are generated only after persistence has completed successfully.

Every completed transaction must be reproducible through audit records.

---

# Payment Domain Responsibilities

The Payments domain is responsible for:

* Payment initialization.
* Gateway communication.
* Transaction verification.
* Signature validation.
* Payment state management.
* Failure handling.
* Duplicate prevention.
* Receipt generation coordination.
* Notification initiation.
* Audit generation.

The Payments domain is not responsible for donation categorization or business reporting.

---

# Supported Payment Providers

## Primary Gateway

Razorpay

Razorpay serves as the primary payment provider for domestic Indian transactions including:

* UPI
* Debit Cards
* Credit Cards
* Net Banking
* Wallets

---

## Secondary Gateway

PayPal

PayPal provides international payment capability for overseas patrons and donors.

---

## Future Gateway Expansion

The architecture supports additional gateway implementations through a common payment contract.

Future providers may include:

* Cashfree
* Binance Pay
* Coinbase Commerce
* NOWPayments
* TripleA

Future integrations should implement the same payment contract without modifying existing business workflows.

---

# Donation Categories

The system supports multiple donation purposes.

Examples include:

* General Trust Donation
* Temple Maintenance
* Gaushala Construction
* Land Development
* Festival Sponsorship
* Annadanam
* Pooja Sponsorship
* Special Campaigns

Each donation category represents a business purpose independent of the payment provider.

The payment system processes transactions identically regardless of donation category.

---

# Anonymous Donations

The system supports anonymous donations.

Anonymous donations intentionally limit personally identifiable information while preserving the financial records required for operational accounting.

Anonymous donations remain subject to the same verification, auditing, and payment processing workflow as standard donations.

---

# Payment Lifecycle

The canonical payment workflow is:

Donation Request

↓

Business Validation

↓

Donation Created (Pending)

↓

Payment Initialization

↓

Gateway Processing

↓

Gateway Callback

↓

Signature Verification

↓

Payment Verification

↓

Database Commit

↓

Receipt Generation

↓

Notification Dispatch

↓

Audit Record

↓

Completed

Only after successful verification may the payment transition into a completed state.

---

# Payment State Model

Payments progress through the following lifecycle:

Created

↓

Initiated

↓

Processing

↓

Authorized

↓

Captured

↓

Verified

↓

Completed

Failure transitions move into the dedicated Failure State Workflow.

---

# Donation State Model

Donations progress independently from payments.

Draft

↓

Pending Payment

↓

Payment Verified

↓

Receipt Generated

↓

Completed

Donation state changes are driven by verified payment events.

---

# Payment Verification

Every payment received from an external provider must undergo verification.

Verification includes:

* Gateway callback validation.
* Signature verification.
* Transaction authenticity checks.
* Duplicate transaction detection.
* Business validation.

Only verified payments may update persistent financial records.

---

# Duplicate Protection

Payment gateways may resend callbacks.

Duplicate processing must never create duplicate donations or duplicate receipts.

Each verified payment is processed exactly once.

Subsequent callbacks referencing an already verified transaction are acknowledged without repeating business operations.

The payment subsystem therefore remains idempotent.

---

# Failure State Architecture

Failures are treated as a dedicated operational workflow rather than being embedded throughout the payment lifecycle.

Whenever a payment cannot successfully transition into a verified state, control passes into the Failure State Workflow.

Payment Processing

↓

Failure Detected

↓

Failure Classification

↓

Recoverable

OR

Terminal

Recoverable failures may be retried automatically where appropriate.

Examples include:

* Temporary gateway communication failures.
* Receipt generation interruptions.
* Notification delivery failures.
* Temporary infrastructure outages.

Terminal failures require manual review.

Examples include:

* Invalid gateway signatures.
* Payment tampering.
* Fraud detection.
* Invalid transaction data.
* Irrecoverable gateway rejection.

Separating failure handling from the primary payment workflow preserves semantic clarity while simplifying operational recovery.

---

# Receipt Generation

Receipts are generated synchronously immediately after successful payment verification and database persistence.

Receipt generation is considered part of the successful business transaction.

Receipt metadata is stored within PostgreSQL.

Physical receipt files are managed through Laravel Storage.

Each receipt maintains:

* Receipt Identifier
* Payment Reference
* Donation Reference
* Storage Location
* Content Hash
* Generation Timestamp

---

# Notification Workflow

Successful payment completion triggers notification generation.

Version One supports:

* Email confirmation

Future notification providers may include:

* WhatsApp
* SMS

Payment processing communicates only with the Notification Service.

Business workflows remain independent of communication providers.

---

# Logging Strategy

The payment subsystem produces deterministic operational logs describing every major financial event.

Examples include:

* Payment initialized.
* Gateway callback received.
* Signature verified.
* Payment verified.
* Receipt generated.
* Notification dispatched.
* Failure detected.
* Failure classification.
* Retry execution.
* Manual intervention required.

Operational logging provides complete traceability throughout the payment lifecycle.

---

# Audit Strategy

Every completed financial transaction produces an audit record.

Audit records include:

* Transaction Identifier
* Donation Identifier
* Payment Provider
* Payment State
* Donation State
* Timestamp
* Processing Metadata

Audit records provide a complete historical record for operational review and financial reconciliation.

---

# Operational Responsibilities

## Laravel

Coordinates business workflows.

Executes services.

Validates business rules.

Dispatches notifications.

Generates receipts.

---

## Neon PostgreSQL

Stores authoritative business data.

Persists donations.

Persists payment records.

Stores audit history.

Stores receipt metadata.

Maintains operational consistency.

---

## Redis

Redis serves as the operational runtime layer.

Responsibilities include:

* Queue processing.
* Temporary payment state.
* Webhook processing.
* Idempotency keys.
* Cache management.
* Runtime coordination.

Redis is not the
