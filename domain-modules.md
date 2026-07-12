# DOMAIN_MODEL.md

# Domain Model

## Purpose

This document defines the business language of the Temple Trust Management System.

The Domain Model establishes the core business entities, their responsibilities, relationships, lifecycles, and invariants. It intentionally avoids implementation details such as database tables, API endpoints, or Laravel classes.

Every future service, migration, model, repository, and user interface should derive from the concepts defined within this document.

The objective is to ensure that the software accurately represents the operational reality of the Temple Trust rather than allowing implementation details to define the business.

---

# Domain Philosophy

The application models the operational activities of a single Temple Trust.

Every business entity exists because it represents something meaningful to the organization.

The system deliberately separates business intent from technical implementation.

For example:

A Donation is not a Payment.

A Payment is not a Receipt.

A Campaign is not a CMS Page.

Each represents a different business concept with different responsibilities.

Maintaining these semantic boundaries preserves clarity throughout the architecture.

---

# Core Business Entities

Version One consists of the following business entities:

* Donation
* Payment
* Receipt
* Campaign
* Static Page
* Gallery
* Gallery Image
* Event

These entities form the canonical business vocabulary of the repository.

---

# Donation

## Definition

A Donation represents the business intent of a patron to financially contribute toward the Temple Trust.

A Donation describes why a contribution is made rather than how money is transferred.

A Donation exists independently from payment processing.

---

## Purpose

The Donation entity exists to represent charitable intent.

It records the purpose, campaign, donor information (where provided), and business context associated with a contribution.

---

## Relationships

A Donation:

* belongs to one Campaign
* may have one Payment
* may produce one Receipt after successful payment verification

---

## Lifecycle

Created

↓

Pending Payment

↓

Payment Verified

↓

Receipt Generated

↓

Completed

---

## Business Invariants

A Donation:

* belongs to exactly one Campaign
* cannot be completed before payment verification
* cannot generate a receipt without a verified payment
* may be anonymous
* must always represent a single business purpose

---

## Future Evolution

Future versions may support recurring donations, scheduled donations, and donor history.

---

# Payment

## Definition

A Payment represents a verified financial transaction.

It fulfills a Donation through an approved payment provider.

A Payment is infrastructure.

It is not the business reason for the contribution.

---

## Purpose

The Payment entity records the financial transaction required to complete a Donation.

It owns transaction verification, gateway state, and financial processing.

---

## Relationships

A Payment:

* fulfills one Donation
* generates one Receipt
* belongs to one payment provider

---

## Lifecycle

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

Failures transition into the dedicated Failure State Workflow defined by the Payment Architecture.

---

## Business Invariants

A Payment:

* cannot complete without verification
* cannot generate multiple receipts
* cannot bypass signature verification
* is processed exactly once

---

## Future Evolution

Future payment providers may be introduced without altering the Payment entity.

---

# Receipt

## Definition

A Receipt represents the official acknowledgement of a successfully verified financial contribution.

---

## Purpose

The Receipt provides an immutable record confirming that a Donation has been fulfilled through a verified Payment.

---

## Relationships

A Receipt:

* belongs to one Donation
* belongs to one Payment

---

## Lifecycle

Generated

↓

Delivered

↓

Archived

---

## Business Invariants

A Receipt:

* cannot exist without a verified Payment
* cannot reference multiple Donations
* remains immutable after generation

---

## Future Evolution

Future versions may support regenerated copies and digitally signed receipts.

---

# Campaign

## Definition

A Campaign represents a fundraising initiative or charitable objective managed by the Temple Trust.

Campaigns define where Donations are directed.

---

## Purpose

Campaigns organize fundraising around specific objectives while allowing the website to present active initiatives to patrons.

Examples include:

* General Trust Fund
* Temple Maintenance
* Gaushala Construction
* Land Development
* Festival Campaign
* Annadanam

---

## Relationships

A Campaign:

* receives many Donations
* is presented through the CMS

---

## Lifecycle

Draft

↓

Active

↓

Completed

↓

Archived

Campaign lifecycle management is controlled through the administrative interface.

---

## Business Invariants

A Campaign:

* may receive many Donations
* remains immutable once archived
* cannot receive new Donations after completion unless reactivated

---

## Future Evolution

Future versions may include fundraising targets, progress indicators, and campaign analytics.

---

# Static Page

## Definition

A Static Page represents fixed public-facing content managed through the CMS.

Static Pages define the permanent informational structure of the website.

---

## Purpose

The Static Page entity enables administrators to manage website content without altering application code.

Core pages include:

* Home
* About
* Contact
* Donate
* Certifications

Administrators may update content but cannot create arbitrary new core pages.

---

## Relationships

Static Pages may reference:

* Hero Banners
* Campaigns
* Gallery Images

---

## Lifecycle

Draft

↓

Published

↓

Updated

↓

Archived

---

## Business Invariants

Static Pages:

* belong to the predefined website structure
* cannot be arbitrarily created or deleted
* exist independently from business transactions

---

## Future Evolution

Future versions may support SEO metadata and richer content composition.

---

# Gallery

## Definition

The Gallery represents the collection of visual media presented by the Temple Trust.

---

## Purpose

The Gallery provides a centralized collection of public media associated with the Trust.

---

## Relationships

A Gallery owns many Gallery Images.

Future versions may organize these images into Albums.

---

## Lifecycle

Draft

↓

Published

↓

Archived

---

## Business Invariants

The Gallery:

* owns all Gallery Images
* does not depend upon Campaigns or Payments
* remains independent from CMS content

---

## Future Evolution

Albums become an organizational layer built on top of existing Gallery Images.

---

# Gallery Image

## Definition

A Gallery Image represents a single image belonging to the Gallery.

---

## Purpose

Gallery Images provide visual representation of temple activities, ceremonies, and infrastructure.

---

## Relationships

A Gallery Image belongs to one Gallery.

Future versions may belong to one Album.

---

## Business Invariants

Every Gallery Image:

* belongs to one Gallery
* references one stored media asset
* remains presentation content rather than business data

---

# Event

## Definition

An Event represents a public activity organized by the Temple Trust.

---

## Purpose

Events communicate upcoming temple activities through the public website.

Version One intentionally focuses on informational publishing.

---

## Relationships

Each Event contains:

* Title
* Date
* Description
* Banner

---

## Lifecycle

Draft

↓

Published

↓

Completed

↓

Archived

---

## Business Invariants

Events:

* exist independently from Donations
* do not own Payments
* remain informational during Version One

---

## Future Evolution

Future versions may introduce registrations, attendance, bookings, and volunteer participation.

---

# Entity Relationships

The business relationships of Version One are intentionally simple.

```text
Campaign
    │
    └────── receives ──────► Donation
                                 │
                                 └──── fulfilled by ───► Payment
                                                             │
                                                             └──── generates ───► Receipt

Static Page
    │
    ├──── references Campaigns
    └──── references Gallery Images

Gallery
    │
    └──── owns ─────► Gallery Images

Event
    │
    └──── independent business entity
```

---

# Domain Invariants

The following rules are always true throughout the system.

* Every Donation belongs to one Campaign.
* Every Payment fulfills one Donation.
* Every Receipt references one verified Payment.
* Payments are verified before persistence.
* Campaigns own fundraising objectives.
* Static Pages define the fixed structure of the website.
* Gallery owns all Gallery Images.
* Events remain independent from financial workflows.
* Business entities communicate through services rather than direct coupling.

These invariants represent the semantic rules of the business and should remain true regardless of implementation.

---

# Version One Scope

The first production release intentionally focuses on a constrained business model.

Included:

* Donations
* Payments
* Receipts
* Campaigns
* Static Pages
* Gallery
* Events

Deferred:

* Authentication
* Administrative Roles
* Albums
* Notifications
* Volunteer Management
* Bookings
* Multi-Temple Support
* Advanced Analytics

Architectural simplicity is preferred over speculative expansion.

---

# Closing Statement

The Domain Model defines the language of the Temple Trust Management System.

Every future implementation should preserve the meaning of the entities described in this document. Business services, database schemas, APIs, and user interfaces exist to implement these concepts—not redefine them.

By establishing a shared business vocabulary before implementation, the repository ensures that future development remains consistent, semantically correct, and aligned with the operational needs of the Temple Trust.
