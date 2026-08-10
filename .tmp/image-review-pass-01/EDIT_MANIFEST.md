# Image Review Pass 01

Status: review-only candidates. Nothing in this directory is wired to the CMS database.

## Safety boundary

- Current delivery assets were copied to `source-snapshots/` before this pass.
- Candidate outputs belong in `candidates/` only.
- No database rows or JSONB references are changed in this pass.
- No crop is allowed unless explicitly recorded and visually approved.
- Rotation-only edits preserve the complete source frame.

## Candidate policy

Each candidate must record:

- source path
- destination path
- rotation applied
- crop status
- intended web slot
- visual review status
- notes about main-subject preservation

## Review status vocabulary

- `pending`: not yet visually reviewed
- `approved-candidate`: visually reviewed and suitable for trustee review
- `hold`: ambiguous; do not wire
- `rejected`: unsuitable source or edit

## Candidates

| Candidate | Source | Rotation | Crop | Intended slot | Status | Notes |
|---|---|---:|---|---|---|---|
| `edited-photo-05.jpg.webp` | `storage/app/public/cms-media/edited-photo-05.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-06.jpg.webp` | `storage/app/public/cms-media/edited-photo-06.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-07.jpg.webp` | `storage/app/public/cms-media/edited-photo-07.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-08.jpg.webp` | `storage/app/public/cms-media/edited-photo-08.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-09.jpg.webp` | `storage/app/public/cms-media/edited-photo-09.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-11.jpg.webp` | `storage/app/public/cms-media/edited-photo-11.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-13.jpg.webp` | `storage/app/public/cms-media/edited-photo-13.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-14.jpg.webp` | `storage/app/public/cms-media/edited-photo-14.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-15.jpg.webp` | `storage/app/public/cms-media/edited-photo-15.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-17.jpg.webp` | `storage/app/public/cms-media/edited-photo-17.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-18.jpg.webp` | `storage/app/public/cms-media/edited-photo-18.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-19.jpg.webp` | `storage/app/public/cms-media/edited-photo-19.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-20.jpg.webp` | `storage/app/public/cms-media/edited-photo-20.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-21.jpg.webp` | `storage/app/public/cms-media/edited-photo-21.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-22.jpg.webp` | `storage/app/public/cms-media/edited-photo-22.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-23.jpg.webp` | `storage/app/public/cms-media/edited-photo-23.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-24.jpg.webp` | `storage/app/public/cms-media/edited-photo-24.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-26.jpg.webp` | `storage/app/public/cms-media/edited-photo-26.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-27.jpg.webp` | `storage/app/public/cms-media/edited-photo-27.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-28.jpg.webp` | `storage/app/public/cms-media/edited-photo-28.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo-copy.jpg.webp` | `storage/app/public/cms-media/edited-photo-copy.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo.jpg.webp` | `storage/app/public/cms-media/edited-photo.jpg.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `edited-photo.png.webp` | `storage/app/public/cms-media/edited-photo.png.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-awards-01-chaganti.webp` | `storage/app/public/cms-media/journal-awards-01-chaganti.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-awards-02-vivekananda.webp` | `storage/app/public/cms-media/journal-awards-02-vivekananda.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-awards-03-shishya-celebration.webp` | `storage/app/public/cms-media/journal-awards-03-shishya-celebration.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-awards-04-sarvabhouma.webp` | `storage/app/public/cms-media/journal-awards-04-sarvabhouma.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-awards-05-south-indian.webp` | `storage/app/public/cms-media/journal-awards-05-south-indian.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-community-01-village-group.webp` | `storage/app/public/cms-media/journal-community-01-village-group.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-community-02-ceremony-with-priest.webp` | `storage/app/public/cms-media/journal-community-02-ceremony-with-priest.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-community-03-ashram-boys.webp` | `storage/app/public/cms-media/journal-community-03-ashram-boys.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-community-04-ashram-adults.webp` | `storage/app/public/cms-media/journal-community-04-ashram-adults.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-community-05-villagers.webp` | `storage/app/public/cms-media/journal-community-05-villagers.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-community-06-booklets.webp` | `storage/app/public/cms-media/journal-community-06-booklets.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-cultural-02-bharatanatyam-guru.webp` | `storage/app/public/cms-media/journal-cultural-02-bharatanatyam-guru.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-cultural-03-bharatanatyam-hyderabad.webp` | `storage/app/public/cms-media/journal-cultural-03-bharatanatyam-hyderabad.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-cultural-04-literary-tribute.webp` | `storage/app/public/cms-media/journal-cultural-04-literary-tribute.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-cultural-05-sangam-collage.webp` | `storage/app/public/cms-media/journal-cultural-05-sangam-collage.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-festivals-01-girija-kalyana.webp` | `storage/app/public/cms-media/journal-festivals-01-girija-kalyana.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-kalyanam-02.webp` | `storage/app/public/cms-media/journal-kalyanam-02.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-kalyanam-03.webp` | `storage/app/public/cms-media/journal-kalyanam-03.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-pooja-03-procession.webp` | `storage/app/public/cms-media/journal-pooja-03-procession.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-pooja-04-sacred-post.webp` | `storage/app/public/cms-media/journal-pooja-04-sacred-post.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |
| `journal-pooja-05-temple-entrance.webp` | `storage/app/public/cms-media/journal-pooja-05-temple-entrance.webp` | 90° CCW | none | review candidate | approved-candidate | full frame preserved; not wired |

## Deferred

Images that require cropping, semantic reassignment, privacy review, or uncertain orientation remain deferred until separately reviewed. This pass must not force every image into a slot.
