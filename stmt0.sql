/* CREATE EXTENSION stripped for sqlite */
/* CREATE EXTENSION stripped for sqlite */
/* CREATE EXTENSION stripped for sqlite */          -- enables EXCLUDE on static_pages.is_homepage (INTEGER has no native GiST opclass)
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
/* CREATE TYPE ENUM stripped for sqlite */
CREATE TABLE currencies (
    code               CHAR(3)        PRIMARY KEY,
    name               TEXT           NOT NULL,
    symbol             TEXT           NOT NULL,
    minor_unit_digits  SMALLINT       NOT NULL
                       CHECK (minor_unit_digits BETWEEN 0 AND 4),
    is_active          INTEGER        NOT NULL DEFAULT TRUE,
    display_order      INT            NOT NULL DEFAULT 0,
    created_at         TEXT    NOT NULL DEFAULT NOW(),
    updated_at         TEXT    NOT NULL DEFAULT NOW()
);