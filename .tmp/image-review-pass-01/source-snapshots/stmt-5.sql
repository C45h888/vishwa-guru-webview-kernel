UPDATE static_pages SET
  homepage_content = jsonb_set(
    jsonb_set(
      jsonb_set(
        jsonb_set(
          homepage_content,
          '{story,image_file_id}','"cms_media_01KYSWKY6K6CYGVHT4M30VJRZ8"'::jsonb),
        '{programs,0,image_file_id}','"cms_media_01KYSWKY6KBN41R5WG0ENXCSTW"'::jsonb),
      '{programs,1,image_file_id}','"cms_media_01KYSWKY6MSEN4TBAZ0Y2QMT7N"'::jsonb),
    '{programs,2,image_file_id}','"cms_media_01KYSWKY6KWNY11F3P4RRAPKJE"'::jsonb),
  updated_at=NOW(),updated_by='system'
WHERE slug='home';
