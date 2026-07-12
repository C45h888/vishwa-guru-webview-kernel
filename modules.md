# MODULES.md

# Business Module Architecture

## Purpose

This document defines the business modules that compose the Temple Trust Management System.

The application is organized around business capabilities rather than technical layers. Every module represents a distinct business domain with clearly defined responsibilities, ownership boundaries, and service contracts.

The purpose of this document is to establish explicit ownership throughout the repository, preventing architectural drift as the application evolves.

Every developer and AI coding agent should understand these module boundaries before introducing new functionality.

---

# Architectural Philosophy

The application follows a modular service-oriented architecture.

Each module owns a specific business capability.

Modules remain cohesive and communicate through explicit service contracts.

Business logic remains encapsulated within the module that owns it.

Modules should never directly manipulate another module's internal implementation.

Cross-domain collaboration occurs only through publicly exposed services.

This architecture preserves maintainability, minimizes coupling, and allows individual business capabilities to evolve independently.

---

# Domain Dependency Principles

The repository follows several dependency rules.

Business modules should remain independent whenever possible.

Dependencies always flow toward shared infrastructure rather than creating circular relationships.

Every module owns its own services, repositories, requests, controllers, and business workflows.

Business ownership is considered more important than technical organization.

---

# Module Dependency Graph

The current domain dependency graph is intentionally minimal.

```text
Payments
│
└──── Shared

Donations
│
├──── Payments
└──── Shared

CMS
│
└──── Shared

Gallery
│
└──── Shared

Events
│
└──── Shared

Authentication (Deferred)
│
└──── Shared
```

The dependency graph illustrates which modules are permitted to communicate directly.

Modules should not introduce additional dependencies without architectural review.

---

# Payments Module

## Purpose

The Payments module owns every financial transaction processed by the system.

This module represents the financial infrastructure of the application and remains independent from the Donations domain.

Payments are responsible for verifying financial transactions rather than defining the business purpose behind them.

---

## Responsibilities

The Payments module owns:

* Payment gateway abstraction
* Razorpay integration
* PayPal integration
* Future payment providers
* Payment lifecycle
* Gateway communication
* Webhook processing
* Signature verification
* Payment verification
* Receipt generation
* Payment state management
* Failure State Manager
* Payment auditing

---

## Public Services

The Payments module exposes services responsible for:

* Initializing payments
* Verifying transactions
* Processing callbacks
* Managing payment states
* Coordinating receipt generation
* Managing payment failures

---

## Dependencies

Depends on:

* Shared

Independent from:

* CMS
* Gallery
* Events

The Donations module communicates with Payments through service contracts.

---

## Does NOT Own

The Payments module does not own:

* Donation categories
* Donation campaigns
* CMS content
* Events
* Gallery assets
* Public website pages

---

# Donations Module

## Purpose

The Donations module represents the business intent behind every contribution made to the Temple Trust.

A donation is a business concept.

It describes why a patron contributes rather than how the payment is processed.

---

## Responsibilities

The Donations module owns:

* Donation records
* Donation intent
* Anonymous donations
* Donation campaigns
* Donation categories
* Donation lifecycle
* Business purpose classification

Examples include:

* General Trust Donation
* Temple Maintenance
* Gaushala Construction
* Land Development
* Festival Campaigns
* Annadanam
* Special Projects

---

## Public Services

The Donations module exposes services responsible for:

* Creating donation requests
* Managing donation categories
* Managing donation campaigns
* Recording donor information
* Coordinating donation workflows

---

## Dependencies

Depends on:

* Payments
* Shared

---

## Does NOT Own

The Donations module does not own:

* Payment verification
* Payment gateways
* Gateway callbacks
* Receipt generation
* Payment failures

---

# CMS Module

## Purpose

The CMS module manages every editable public-facing page of the website.

It enables administrators to update website content without modifying application code.

The CMS exists solely to manage presentation content.

It does not own business workflows.

---

## Responsibilities

The CMS module owns:

* Home Page
* About Page
* Blog Page
* Donation Campaign Pages
* Hero Banners
* Contact Information

Future versions may include:

* SEO metadata
* Additional informational pages
* Navigation management

---

## Public Services

The CMS module exposes services responsible for:

* Publishing content
* Updating page content
* Managing hero banners
* Updating contact information

---

## Dependencies

Depends on:

* Shared

---

## Does NOT Own

The CMS module does not own:

* Donations
* Payments
* Gallery
* Events

---

# Gallery Module

## Purpose

The Gallery module manages visual media associated with the Temple Trust.

The initial implementation intentionally remains simple.

---

## Responsibilities

The Gallery module owns:

* Albums
* Images

Future versions may introduce:

* Videos
* Featured galleries
* Categories

---

## Public Services

The Gallery module exposes services responsible for:

* Album management
* Image management
* Gallery publishing

---

## Dependencies

Depends on:

* Shared

---

## Does NOT Own

The Gallery module does not own:

* CMS pages
* Donations
* Payments
* Events

---

# Events Module

## Purpose

The Events module manages temple events published through the website.

The initial implementation intentionally focuses on information publishing rather than registrations.

---

## Responsibilities

Each event contains:

* Title
* Date
* Description
* Banner

Future versions may support:

* Registration
* Bookings
* Attendance
* Volunteer coordination

---

## Public Services

The Events module exposes services responsible for:

* Event creation
* Event publishing
* Event updates
* Event archiving

---

## Dependencies

Depends on:

* Shared

---

## Does NOT Own

The Events module does not own:

* Payments
* Donations
* Gallery
* CMS

---

# Shared Module

## Purpose

The Shared module provides reusable infrastructure used throughout the application.

It exists to eliminate duplication while preserving clear business ownership.

The Shared module is not a business domain.

---

## Responsibilities

The Shared module owns:

* Configuration
* Constants
* Enumerations
* Common interfaces
* Shared DTOs
* Utility classes
* Base exceptions

---

## Dependencies

Every business module may depend upon the Shared module.

The Shared module depends upon no business module.

---

## Does NOT Own

The Shared module must never become a container for business logic.

Business workflows always remain inside their owning modules.

---

# Authentication Module (Deferred)

Authentication is intentionally excluded from Version One.

The first production release focuses on delivering a secure, reliable public-facing website and payment platform.

Administrative authentication will be introduced after the foundational platform has reached operational stability.

Future responsibilities include:

* Administrative login
* Session management
* Role management
* Permission management
* Administrative authorization

Until that phase begins, Authentication remains outside the active business architecture.

---

# Module Ownership Rules

Every new feature introduced into the repository must belong to an existing business module.

If a feature cannot clearly belong to an existing module, its business ownership should be evaluated before implementation begins.

Modules should not accumulate unrelated responsibilities over time.

Architectural clarity is preserved by expanding business capabilities deliberately rather than allowing modules to evolve into generic containers.

---

# Closing Statement

The Temple Trust Management System is intentionally organized around business domains rather than technical layers.

Each module represents a clearly defined business capability with explicit ownership, dependencies, and responsibilities.

Maintaining these boundaries ensures that the application remains understandable, maintainable, and extensible as new functionality is introduced.

Future modules should extend the architecture through well-defined domain boundaries rather than increasing coupling between existing components.
