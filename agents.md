# AGENTS.md

# AI Engineering Constitution

## Purpose

This document defines the operational rules, architectural directives, and engineering expectations that every AI coding agent must follow while contributing to the Temple Trust Management System.

The objective of this document is to ensure that all implementation decisions remain aligned with the repository's architectural philosophy. AI agents are expected to function as disciplined software engineers operating within an established engineering system rather than autonomous code generators.

The architecture of this repository has been intentionally designed before implementation begins. AI agents must preserve this architectural foundation throughout the lifetime of the project.

---

# Project Overview

The Temple Trust Management System is a backend-first Laravel application designed to manage the operational workflows of a single temple trust.

The system is responsible for handling donations, payment processing, content management, events, galleries, and supporting business operations. Administrative workflows and user authentication are Phase 4 forward work and are not part of this constitution.

The repository prioritizes financial integrity, maintainability, security, and long-term operational stability over rapid feature delivery.

Every implementation must reinforce these principles.

---

# Technology Stack

Framework

Laravel

Programming Language

PHP

Frontend

Svelte 5 (components) + Inertia 2 (server-driven SPA bridge)

Tailwind CSS + shadcn-svelte (bits-ui) UI primitives

Frontend Build

Vite 5 + laravel-vite-plugin

Frontend Path Aliases

$shared/* → resources/js/shared/*, $domains/* → resources/js/domains/*

Database

Neon PostgreSQL

ORM

Laravel Eloquent

Primary Payment Gateway

Razorpay

Secondary Payment Gateway

PayPal

Caching & Idempotency Backend

Redis (active — SETEX dedupe for webhook + idempotency, resolved-page cache)

Notifications

Email (channel reserved; Notifications module lands in Phase 4)

---

# Engineering Philosophy

The repository follows a backend-first development philosophy.

Business rules define user interfaces.

User interfaces never define business rules.

Every feature should first exist as a backend capability before presentation components are implemented.

The backend is the authoritative implementation of business behavior.

The frontend exists solely to expose validated backend workflows.

---

# Architectural Authority

The architectural center of the application is the Service Layer.

Controllers coordinate requests.

Services implement business workflows.

Repositories perform persistence.

Models represent data.

Svelte 5 components render presentation through Inertia 2, driven by Inertia::render responses from controllers. The single Blade template in the runtime (resources/views/app.blade.php) exists ONLY as the Inertia root view shell.

AI agents must preserve this hierarchy.

---

# Primary Engineering Principles

Every implementation should satisfy the following principles.

Financial integrity takes precedence over convenience.

Security takes precedence over development speed.

Maintainability takes precedence over cleverness.

Framework conventions take precedence over unnecessary abstraction.

Explicit business workflows take precedence over implicit behavior.

Readable code takes precedence over compact code.

---

# Domain Organization

The repository is organized around business modules rather than technical folders.

The kernel is composed of ten modules grouped by surface:

Public surface

Campaigns     — donation causes; owns CampaignsQueryContract

Cms           — static pages, hero banners, contact information; owns StaticPageRendererContract + PublicMediaPresentationService

Events        — public read surface for temple events; owns EventsQueryContract

Gallery       — public read surface for photo galleries; owns GalleryQueryContract

Money surface

Payments      — gateway adapters (Razorpay, PayPal, InMemory), state machines, repositories; owns CampaignQueryContract (cross-kernel Shape A bridge)

Infrastructure kernels

Persistence   — owns PersistenceAdapterContract + RepositoryRegistryContract

Redis         — owns RedisConnectorContract; service code depends on the contract, not Illuminate\Support\Facades\Redis

Queue         — owns QueueConnectorContract; same doctrine as Redis

Runtime       — health probes, FailureRouter, EnvValidator, runtime diagnostics

Architectural foundation

Shared        — ConfigurationContract, EnvironmentContract, Clock, IdentifierGenerator, ConfigurationRegistry; every other kernel depends on it

Donations are not a module. The cause-side lifecycle (slug, target, dates, featured flag) lives in Campaigns; the money-side lifecycle (intent, verification, capture, refund, receipt) lives in Payments. The two lifecycles meet only at PaymentService::initialize, where a DonationIntent is built from a campaign_id.

Each module owns its own services, controllers, requests, repositories, policies, and related resources.

Modules should remain cohesive and self-contained.

---

# Service Layer Rules

Business logic belongs exclusively within services.

Controllers must remain thin.

Repositories encapsulate persistence.

Services may coordinate with other services when implementing complete business workflows.

Business logic must never be implemented inside:

Controllers

Svelte components and route handlers

Routes

Middleware

Models (except simple accessors, mutators, and relationships)

---

# Controller Responsibilities

Controllers should:

Receive validated requests.

Call services.

Return responses.

Redirect users where necessary.

Return Inertia::render responses for page requests and JSON for mutating endpoints. Never mix.

Controllers should not:

Contain business rules.

Execute payment verification.

Implement complex queries.

Perform financial calculations.

Coordinate multiple business workflows.

---

# Repository Responsibilities

Repositories own persistence operations.

Services should interact with repositories rather than directly implementing complex Eloquent operations.

Repositories should remain focused on storage concerns and avoid business logic.

---

# Payment Directives

Financial integrity is a foundational principle.

No donation should be considered successful until the payment gateway has verified the transaction.

The canonical payment workflow is:

Request Validation

↓

Payment Initialization

↓

Gateway Processing

↓

Webhook Callback

↓

Signature Verification

↓

Database Persistence

↓

Receipt Generation

↓

Notification (Phase 4 — receipt delivery is currently a PDF download stub; see Receipt.svelte:99)

↓

Audit

AI agents must never bypass gateway verification.

AI agents must never trust client-side payment responses.

---

# Database Directives

PostgreSQL is the authoritative source of operational data.

Business entities are persisted only after successful validation and verification.

Repositories own database interactions.

Database schema modifications must be performed through Laravel migrations.

Direct database modifications outside migrations are prohibited.

---

# File Storage

Large files should never be embedded inside PostgreSQL.

The database stores metadata describing uploaded files.

Laravel Storage manages physical file persistence.

Uploaded assets include:

Temple Images

Donation Receipts

Trust Documents

Certificates

Gallery Assets

Videos

Public media is served through the /media/{id} route (Public/CmsMedia/ShowController). Files are streamed from Laravel Storage with ETag + Cache-Control headers; bytes are never embedded as base64 or persisted as PostgreSQL bytea.

---

# Notification Directives

Business services should communicate with the Notification Service. Business logic must never directly invoke email providers. Future communication providers should remain interchangeable.

Phase 4 status: the Notifications module is not yet built. Receipt delivery currently falls back to a PDF download link in resources/js/domains/payments/Receipt.svelte:99. When the Notifications module lands, the existing ReceiptDeliveryState enum (app/Payments/Domain/Enums/) and the ReceiptService's delivery channel (app/Payments/Services/ReceiptService.php) are the integration points.

---

# Security Directives

Validate all external input.

Never trust client-side state.

Enforce authorization before business execution.

Protect financial operations through gateway verification.

Use Laravel's native security features (CSRF, encrypted cookies, session) supplemented by sanctified third-party integrations (Inertia, gateway SDKs, dompdf) where they earn their place.

Store secrets only in environment configuration.

Never expose sensitive credentials.

Prefer Laravel-native solutions, but do not avoid sanctified third-party packages — inertiajs/inertia-laravel, @inertiajs/svelte, razorpay/razorpay, paypal/paypal-checkout-sdk, barryvdh/laravel-dompdf, bits-ui — when they are the canonical implementation of a subsystem the constitution recognises.

---

# AI Decision Framework

Before implementing any feature, evaluate the following questions:

Does this implementation preserve financial integrity?

Does this implementation respect domain boundaries?

Does this implementation keep business logic inside services?

Does this implementation follow Laravel conventions?

Can another developer easily understand this implementation six months from now?

Would this implementation remain maintainable if the project doubles in size?

If the answer to any question is no, reconsider the implementation.

---

# Coding Expectations

Prefer readability over cleverness.

Prefer explicit implementations over implicit behavior.

Prefer composition over duplication.

Prefer dependency injection over static coupling.

Prefer small cohesive services over large multi-purpose classes.

Avoid premature optimization.

Avoid unnecessary abstraction.

Avoid introducing additional dependencies without clear architectural justification.

---

# Documentation Responsibilities

Whenever introducing a new domain, architectural pattern, or significant workflow, update the relevant documentation.

Documentation should evolve alongside implementation.

Architectural drift caused by undocumented decisions is considered a defect.

---

# Scope

The application currently targets a single temple trust.

Do not introduce multi-tenant abstractions.

Do not implement speculative scalability features.

Future architectural evolution should occur only when justified by business requirements.

The frontend stack is Svelte 5 + Inertia 2 + Tailwind + shadcn-svelte (bits-ui). Do not introduce Blade views or Alpine.js for public pages without explicit reconciliation of this constitution.

Phase 4 work (Authentication module, Notifications module, admin CMS editing surface) must not be partially implemented. Either build them behind their own constitution section or leave them out.

---

# Phase 4: Admin Kernel

Lands now (per user decision 2026-08-02). The Authentication module + admin CMS editing surface have their own dedicated constitution section, satisfying the "must not be partially implemented" rule above.

## Scope

The admin kernel has exactly TWO surfaces:

  1. Campaigns — admin can edit existing campaigns, create new ones (Pass 2).
  2. Events — admin can create new upcoming events and end upcoming events to move them into the past-events surface (Pass 3).

Both surfaces are gated behind a single canonical admin role (`'admin'`). One admin, env-driven credentials, no public registration. The notifications module remains deferred to its own pass — receipt delivery currently uses the PDF download fallback documented elsewhere.

## Canonical references

  - DB schema: applied via Neon MCP. Source of truth is the live Neon `br-shiny-poetry-aow2d8mt` branch.
  - Laravel migration mirrors: `database/migrations/2026_08_02_000011_create_users_table_postgres.php` (PG, guarded) + `2026_08_02_000012_create_users_table_sqlite.php` (SQLite test mirror).
  - Auth architecture mirrors Laravel Breeze 1.x's `inertia-common` stubs (controllers + middleware + routes) so a future Laravel 11 / Breeze 2.x upgrade is a swap, not a rewrite. The Svelte login page is hand-rolled because Breeze 1.x ships only React/Vue stubs for Laravel 10.

## Authentication surface

  - Login: `POST /login` (form via Inertia on `resources/js/domains/Auth/Login.svelte`).
  - Logout: `POST /logout` (session invalidate + CSRF token regen, Breeze canonical).
  - NOT BUILT (and intentionally absent): register, forgot-password, reset-password, email-verification, confirm-password. Single canonical admin authenticates via env-driven credentials only.
  - Throttle: 5 failed attempts per email+IP per minute (Breeze canonical).

## Authorization model

  - Single role: `'admin'`. CHECK constraint on the table rejects any other value. Multi-role expansion is a follow-on migration, not a code redesign.
  - Middleware aliases in `app/Http/Kernel.php`:
      - `auth`   — Illuminate's canonical, redirects unauthenticated → /login
      - `guest`  — `App\Http\Middleware\RedirectIfAuthenticated` redirects authenticated → /admin
      - `admin`  — `App\Http\Middleware\EnsureUserIsAdmin` enforces `User::isAdmin() === true`, else 403
  - Route group: `/admin/*` mounted under `['web', 'auth', 'admin']` (see `routes/admin.php`).

## Canonical admin seeding

  - `database/seeders/AdminSeeder.php` reads `ADMIN_EMAIL` / `ADMIN_PASSWORD` / `ADMIN_NAME` from env.
  - Idempotent: re-runs update the existing row's password + role + name via `ON CONFLICT (email) DO UPDATE`.
  - Default password `changeme-admin-2026` is intentionally weak so a missing env var produces a loud warning to stderr; production must override.

## Architectural invariants

  - The admin kernel does NOT introduce new domain modules. It is a separate `App\Http\Controllers\Admin\` namespace; business modules (Campaigns, Events) own their own contracts and services, and the admin controllers delegate to those contracts.
  - Admin mutations live in services, not controllers. Pass 2 / Pass 3 add new `CampaignAuthoringService` / `EventAuthoringService` contracts to the existing Campaigns + Events modules; the admin controllers are thin shells around them.
  - The admin uses Inertia + Svelte 5. No Blade admin views, no Alpine.js, no shadcn-svelte variant other than bits-ui.
  - File upload UI is built when the corresponding authoring surface lands (Pass 2 for campaigns, Pass 3 for events). The V1 `StubImageUrlResolver` is replaced when the first upload UI ships.
  - Audit log: `created_by` / `updated_by` columns on `campaigns` / `events` are populated with the admin's user id by the authoring services. Wiring the `audit_events` table is deferred to a follow-on pass — Pass 1 keeps the surface tight.

## Non-goals (deliberately excluded from Pass 1)

  - Cover/banner image upload UI (Pass 2 / Pass 3).
  - State machines (replaced with FormRequest validation, per user decision 2026-08-02).
  - Audit log wiring (Pass 2/3 if needed).
  - Email verification (single canonical admin doesn't need it).
  - Password reset (single canonical admin uses env-var override).
  - Multi-admin management UI (single canonical admin only).

---

# Mission

Every contribution made by an AI coding agent should leave the repository in a cleaner, more maintainable, and more understandable state than it was found.

The objective is not simply to generate code.

The objective is to build a secure, reliable, and maintainable operational platform that faithfully supports the long-term needs of the temple trust while preserving the architectural integrity established by this repository.
