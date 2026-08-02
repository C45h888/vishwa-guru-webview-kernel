UPDATE static_pages SET
  about_page_content = jsonb_set(
    jsonb_set(
      jsonb_set(
        jsonb_set(
          about_page_content,
          '{values,image_file_id}','"cms_media_01KYSWKY6KB7J89PEP8JRVJAD9"'::jsonb),
        '{trustees,0,photo_file_id}','"cms_media_01KYSWKY6K6CYGVHT4M30VJRZ8"'::jsonb),
      '{trustees,1,photo_file_id}','"cms_media_01KYSWKY6MNVQ2QNZTF088BRJ7"'::jsonb),
    '{trustees,2,photo_file_id}','"cms_media_01KYSWKY6MTS9SSKY8FJN0JTNG"'::jsonb),
  updated_at=NOW(),updated_by='system'
WHERE slug='about';
