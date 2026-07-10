# ARCHITECTURE.md

# Temple Trust Management System Architecture

## Purpose

This document defines the architectural foundation of the Temple Trust Management System. It serves as the authoritative engineering reference for the repository and establishes the principles, boundaries, and implementation standards that govern the application.

The objective of this document is not merely to describe the software structure, but to ensure that every future contribution preserves the architectural integrity of the system. Every developer and AI coding agent working within this repository should understand the concepts defined here before implementing new functionality.

---

# Architectural Philosophy

The Temple Trust Management System follows a **backend-first, service-oriented architecture** built on Laravel. The backend is considered the authoritative implementation of the application's business rules and operational workflows.

The system is designed around the principle that business processes should exist independently of the presentation layer. User interfaces are responsible only for presenting workflows that have already been defined by the backend. Business rules must never originate from Blade templates, JavaScript components, or client-side logic.

Financial integrity, security, maintainability, and architectural clarity are considered the four foundational pillars of the application. Every implementation decision should reinforce these principles rather than optimize solely for development speed.

Laravel is intentionally used as the primary application framework because it provides mature solutions for authentication, routing, validation, authorization, queues, scheduling, notifications, and database interaction. The project favors Laravel conventions over unnecessary custom abstractions whenever those conventions satisfy the application's requirements.

---

# Architectural Objectives

The architecture has been designed with the following long-term objectives:

* Preserve financial integrity throughout every payment workflow.
* Keep business logic centralized and independent from presentation.
* Maintain clear domain boundaries.
* Favor modular organization over framework-centric organization.
* Ensure code remains understandable by both developers and AI agents.
* Support long-term maintainability.
* Minimize architectural complexity.
* Keep infrastructure replaceable without affecting business logic.
* Build a stable operational platform rather than a collection of independent pages.

---

# System Overview

The application is implemented as a modular service-oriented Laravel application.

Business domains remain isolated from one another while collaborating through explicit service contracts.

Controllers coordinate HTTP requests.

Services execute business workflows.

Repositories perform persistence operations.

Eloquent models represent the underlying data.

The database stores the authoritative operational state of the application.

The frontend is responsible only for rendering business workflows that have already been validated by the backend.

---

# Core Architectural Principles

## Backend First Development

Development always begins with backend implementation.

Business workflows, validation rules, authorization policies, payment processing, service contracts, and persistence are completed before frontend development begins.

Frontend components should never dictate business behavior.

Instead, they consume stable backend workflows.

---

## Service-Oriented Architecture

The service layer is the architectural center of the application.

Every significant business workflow belongs inside a dedicated service.

Examples include:

* Donation Service
* Payment Service
* Event Service
* Gallery Service
* CMS Service
* Authentication Service
* Notification Service

Services encapsulate business logic and coordinate interactions between domains.

Business rules must never be implemented inside controllers or Blade templates.

---

## Thin Controllers

Controllers exist solely to translate HTTP requests into business operations.

Controllers are responsible for:

* Receiving validated requests.
* Calling the appropriate service.
* Returning responses.
* Handling redirects where appropriate.

Controllers must not:

* Contain business logic.
* Execute payment verification.
* Construct complex database queries.
* Implement business rules.

---

## Repository Layer

Repositories are responsible for all persistence operations.

Business services should not directly manipulate Eloquent models for complex persistence workflows.

Instead, services communicate with repositories that encapsulate database interactions.

This separation keeps business logic independent from storage implementation and allows persistence strategies to evolve without changing domain logic.

---

# Domain Organization

The repository is organized around business capabilities rather than Laravel's default technical folders.

Each domain owns its own controllers, services, repositories, requests, policies, and related resources.

The initial domains are:

* Authentication
* Payments
* Donations
* Events
* Gallery
* CMS
* Notifications (Email)
* Shared

Payments intentionally exist as an independent domain rather than being embedded within Donations. A donation is a business concept, while payment processing is an infrastructure concern. This separation preserves clear domain boundaries and improves maintainability.

---

# Service Communication

Business workflows frequently span multiple domains.

Services are therefore permitted to coordinate with other services when implementing complete business transactions.

A typical donation workflow may follow this sequence:

Donation Service

↓

Payment Service

↓

Receipt Service

↓

Notification Service

Each service remains responsible only for its own domain while collaborating through clearly defined contracts.

Domains must not bypass services to directly manipulate another domain's internal implementation.

---

# Financial Integrity

Financial integrity is considered one of the primary architectural principles of the application.

No payment is considered successful until it has been cryptographically verified through the payment gateway's callback mechanism.

The canonical workflow is:

Donation Request

↓

Input Validation

↓

Payment Initialization

↓

Gateway Processing

↓

Webhook Callback

↓

Signature Verification

↓

Database Commit

↓

Receipt Generation

↓

Email Notification

↓

Audit Record

Only after successful verification may the application persist financial records or generate official receipts.

The application must never rely solely on client-side success responses.

---

# Database Philosophy

PostgreSQL serves as the authoritative persistence layer of the application.

Business entities, financial transactions, operational records, and audit history are stored within the relational database.

The database represents the verified operational state of the system.

Application services validate and verify business operations before persistence occurs.

No financial information should be committed until verification has completed successfully.

---

# File Storage Strategy

The database stores metadata describing uploaded files rather than binary content.

Large files such as:

* Donation receipts
* Temple images
* Gallery assets
* Videos
* Trust documents
* Certificates

are managed through Laravel's storage abstraction.

The database records:

* File identifier
* Storage path
* Hash
* Related entity
* Upload metadata

This strategy preserves database performance while maintaining complete traceability.

---

# Notification Architecture

Notifications remain independent from business workflows.

Business services communicate with the Notification Service rather than directly interacting with email providers.

Initially the application supports:

* Email notifications

Future support may include:

* WhatsApp
* SMS

The Notification Service provides a stable abstraction that prevents changes to communication providers from affecting business logic.

---

# Security Principles

Security is treated as a foundational architectural concern.

The application follows these principles:

* Validate all incoming data.
* Enforce authorization before business execution.
* Protect financial operations through gateway verification.
* Never trust client-side state.
* Store secrets outside source control.
* Apply Laravel's native CSRF protection.
* Use framework-native authentication and authorization.
* Record important operational events.
* Follow the principle of least privilege.

Business logic should always execute within trusted backend services.

---

# Audit Philosophy

The system maintains an auditable operational history.

Important business events should generate audit records describing:

* Actor
* Action
* Entity
* Entity Identifier
* Timestamp
* Relevant metadata

Financial operations should always be traceable.

The database therefore functions as both the operational data store and the primary audit surface for the application.

---

# Technology Stack

Application Framework

Laravel

Programming Language

PHP

Frontend

Blade Templates

Styling

Tailwind CSS

Frontend Interactivity

Alpine.js

Database

Neon PostgreSQL

ORM

Laravel Eloquent

Primary Payment Gateway

Razorpay

Secondary Payment Gateway

PayPal

Notifications

Email

Caching and Queues

Redis (introduced when required)

---

# Dependency Rules

Dependencies always move inward toward the business layer.

Presentation depends on services.

Services depend on repositories.

Repositories depend on Eloquent and PostgreSQL.

Business domains communicate only through service contracts.

No module should directly manipulate another module's internal implementation.

This preserves explicit ownership and prevents architectural coupling.

---

# Current Scope

The current implementation targets a **single temple trust**.

The architecture intentionally avoids premature multi-tenant abstractions.

Future support for multiple trusts or organizations may be introduced through deliberate architectural evolution rather than speculative design.

Every architectural decision made today optimizes for simplicity, maintainability, and correctness within a single deployment.

---

# Engineering Standards

The following rules apply throughout the repository:

* Business logic belongs exclusively in services.
* Controllers remain thin.
* Blade templates remain presentation-only.
* Payment verification is mandatory before persistence.
* Every schema modification uses Laravel migrations.
* Repository methods encapsulate persistence.
* Domain boundaries must remain explicit.
* Framework conventions take precedence over unnecessary abstractions.
* Documentation should evolve alongside implementation.
* Maintainability always takes precedence over cleverness.

---

# Closing Statement

The Temple Trust Management System is intended to become the operational software platform supporting the trust's day-to-day activities. The architecture therefore prioritizes reliability, financial integrity, security, and long-term maintainability over unnecessary complexity.

Every feature added to the system should reinforce these architectural principles. Contributions should extend the existing design rather than circumvent it, ensuring that the application remains coherent, understandable, and dependable throughout its lifetime.
