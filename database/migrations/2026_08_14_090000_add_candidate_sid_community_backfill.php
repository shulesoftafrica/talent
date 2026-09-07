<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Keeps talent.candidates.sid in step with the durable ShuleSoft community id.
 *
 * `sid` is the fixed cross-application key (email/phone are mutable). It is
 * assigned by the ShuleSoft community and must be identical for the same person
 * across every schema (shulesoft.teacher/student, admin.users, safaribook.users,
 * academy.users, talent.candidates). This migration:
 *   1. adds talent.lookup_community_sid(email, phone) — resolves the authoritative
 *      sid from the community core tables (same email/last-9-phone match the
 *      Academy OTP resolver uses), core tables first;
 *   2. adds a BEFORE INSERT/UPDATE trigger so a candidate's sid is set/refreshed
 *      whenever their identity is written — no app code change needed;
 *   3. backfills existing rows.
 * Where a candidate has no community presence yet, their sid is left untouched
 * (no false links). Verified Academy skills then match a candidate by this sid.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION talent.lookup_community_sid(p_email text, p_phone text)
RETURNS integer
LANGUAGE sql STABLE AS $fn$
    WITH ident AS (
        SELECT lower(trim(coalesce(p_email, ''))) AS email,
               NULLIF(right(regexp_replace(coalesce(p_phone, ''), '\D', '', 'g'), 9), '') AS phone9
    ),
    src AS (
        SELECT 1 AS pri, sid,
               lower(coalesce(email, '')) AS email,
               right(regexp_replace(coalesce(phone::text, ''), '\D', '', 'g'), 9) AS phone9
        FROM admin.users WHERE sid IS NOT NULL
        UNION ALL
        SELECT 2, sid, lower(coalesce(email, '')),
               right(regexp_replace(coalesce(phone::text, ''), '\D', '', 'g'), 9)
        FROM shulesoft.teacher WHERE sid IS NOT NULL
        UNION ALL
        SELECT 3, sid, lower(coalesce(email, '')),
               right(regexp_replace(coalesce(phone::text, ''), '\D', '', 'g'), 9)
        FROM shulesoft.student WHERE sid IS NOT NULL
        UNION ALL
        SELECT 4, sid, lower(coalesce(email, '')),
               right(regexp_replace(coalesce(phone::text, ''), '\D', '', 'g'), 9)
        FROM safaribook.users WHERE sid IS NOT NULL
    )
    SELECT src.sid
    FROM src, ident
    WHERE (ident.email <> '' AND src.email = ident.email)
       OR (ident.phone9 IS NOT NULL AND src.phone9 = ident.phone9)
    ORDER BY src.pri ASC, src.sid DESC
    LIMIT 1;
$fn$;

CREATE OR REPLACE FUNCTION talent.set_candidate_sid()
RETURNS trigger
LANGUAGE plpgsql AS $fn$
DECLARE found_sid integer;
BEGIN
    found_sid := talent.lookup_community_sid(NEW.email, NEW.phone);
    IF found_sid IS NOT NULL THEN
        NEW.sid := found_sid;
    END IF;
    RETURN NEW;
END;
$fn$;

DROP TRIGGER IF EXISTS trg_candidate_sid ON talent.candidates;
CREATE TRIGGER trg_candidate_sid
BEFORE INSERT OR UPDATE OF email, phone ON talent.candidates
FOR EACH ROW EXECUTE FUNCTION talent.set_candidate_sid();
SQL);

        // One-time backfill of existing candidates.
        DB::statement(<<<'SQL'
UPDATE talent.candidates c
SET sid = m.new_sid
FROM (SELECT id, talent.lookup_community_sid(email, phone) AS new_sid FROM talent.candidates) m
WHERE m.id = c.id AND m.new_sid IS NOT NULL AND m.new_sid IS DISTINCT FROM c.sid
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_candidate_sid ON talent.candidates;
DROP FUNCTION IF EXISTS talent.set_candidate_sid();
DROP FUNCTION IF EXISTS talent.lookup_community_sid(text, text);
SQL);
    }
};
