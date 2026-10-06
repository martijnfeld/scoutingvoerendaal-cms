# Project instructions

Before analysing or changing this project, read `CLAUDE.md` completely. It is
the authoritative technical map and contains the deployment constraints,
architecture, security rules, migration/release process, and conventions used
by the existing code.

Key constraints: this is plain PHP + MySQL for shared hosting (PHP 7.4+), with
no Composer, npm, framework, build step, or server-side shell dependency.
Keep user-facing copy, comments, and CMS UI in Dutch. For database changes,
update both `sql/install.sql` and a new ordered migration in
`sql/migrations/`.
