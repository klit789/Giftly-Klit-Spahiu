-- Optional, repeatable demo content for the sample Harbour House and New Start campaigns.
-- The gallery photos are labeled illustrations, not Giftly recipient stories or outcomes.
USE giftly;

INSERT INTO change_stories (
  charity_id, created_by, campaign_id, category_id, title, slug, excerpt, description,
  featured_image_path, featured_alt, featured_image_is_illustrative, impact_stats_json,
  status, published_at
)
SELECT
  cr.charity_id,
  NULL,
  cr.id,
  sc.id,
  'Winter layers: 63 items recorded received',
  'winter-layers-63-items-recorded-received',
  'Giftly records show 63 items marked received for Harbour House''s winter-layers campaign. This is a campaign update, not a recipient story; the photo is illustrative.',
  'This is a Giftly campaign update based on donation records, not a personal recipient story. Harbour House''s campaign brief says it is welcoming families moving into secure homes and needs clean, wearable warm layers for adults in Leeds. Giftly records show 63 clothing items attached to pledges marked received for this campaign. "Received" means the charity recorded the donations as received in Giftly; it does not tell us how many people received an item or what happened after distribution. The featured photo is illustrative stock imagery of a clothing rack, not a photo of Harbour House''s collection or a recipient. Photo: Christian Fregnan, CC0, via Wikimedia Commons: https://commons.wikimedia.org/wiki/File:Clothes_hangers_on_railing_(Unsplash).jpg',
  'assets/uploads/stories/giftly-illustrative-clothing-rack.jpg',
  'Illustrative clothing rack; no people or recipients pictured.',
  1,
  '[]',
  'published',
  NOW()
FROM clothing_requests cr
JOIN charities c ON c.id = cr.charity_id
JOIN story_categories sc ON sc.slug = 'winter-support'
WHERE cr.id = 1
  AND cr.title = 'Winter layers for families finding home'
  AND c.organization_name = 'Harbour House'
  AND (
    SELECT COALESCE(SUM(di.quantity), 0)
    FROM donation_items di
    JOIN donation_pledges dp ON dp.id = di.pledge_id
    WHERE dp.request_id = cr.id AND dp.status = 'received'
  ) = 63
  AND NOT EXISTS (SELECT 1 FROM change_stories s WHERE s.slug = 'winter-layers-63-items-recorded-received');

UPDATE change_stories
SET featured_image_path = 'assets/uploads/stories/giftly-illustrative-school-clothing-room.png',
    featured_alt = 'Illustrative clothing in a generic donation room; not New Start Collective''s collection.',
    featured_image_is_illustrative = 1,
    description = REPLACE(
      description,
      'The featured photo is an illustrative garment from Giftly''s wardrobe catalog; it is not a school uniform, recipient photo, or proof of a delivered item.',
      'The featured photo is a Giftly-supplied illustration of folded clothing in a generic donation room; it is not a photo of New Start Collective''s collection, a recipient, or a delivered item.'
    )
WHERE slug = 'school-support-70-items-recorded-received';

INSERT INTO change_stories (
  charity_id, created_by, campaign_id, category_id, title, slug, excerpt, description,
  featured_image_path, featured_alt, featured_image_is_illustrative, impact_stats_json,
  status, published_at
)
SELECT
  cr.charity_id,
  NULL,
  cr.id,
  sc.id,
  'School support: 70 items recorded received',
  'school-support-70-items-recorded-received',
  'Giftly records show 70 items marked received for New Start Collective''s school-uniform campaign. This is a campaign update, not a recipient story; the photo is illustrative.',
  'This is a Giftly campaign update based on donation records, not a personal recipient story. New Start Collective''s campaign brief asks for clean school basics in common primary-age sizes. Giftly records show 70 clothing items attached to pledges marked received for this campaign. "Received" means the charity recorded the donations as received in Giftly; it does not tell us how many children received an item or what happened after distribution. The featured photo is a Giftly-supplied illustration of folded clothing in a generic donation room; it is not a photo of New Start Collective''s collection, a recipient, or a delivered item.',
  'assets/uploads/stories/giftly-illustrative-school-clothing-room.png',
  'Illustrative clothing in a generic donation room; not New Start Collective''s collection.',
  1,
  '[]',
  'published',
  NOW()
FROM clothing_requests cr
JOIN charities c ON c.id = cr.charity_id
JOIN story_categories sc ON sc.slug = 'children-education'
WHERE cr.id = 2
  AND cr.title = 'School uniforms for a confident September'
  AND c.organization_name = 'New Start Collective'
  AND (
    SELECT COALESCE(SUM(di.quantity), 0)
    FROM donation_items di
    JOIN donation_pledges dp ON dp.id = di.pledge_id
    WHERE dp.request_id = cr.id AND dp.status = 'received'
  ) = 70
  AND NOT EXISTS (SELECT 1 FROM change_stories s WHERE s.slug = 'school-support-70-items-recorded-received');

UPDATE happy_gallery_entries
SET image_path = 'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png',
    caption = 'Volunteers handing over clothing donations',
    category_id = (SELECT id FROM gallery_categories WHERE slug = 'community'),
    location_safe = NULL,
    consent_confirmed = 1,
    guardian_consent = 0,
    is_illustrative = 1,
    moderation_status = 'approved',
    is_published = 1,
    reviewed_by = 1,
    reviewed_at = NOW(),
    published_at = COALESCE(published_at, NOW())
WHERE image_path IN (
  'assets/uploads/gallery/giftly-illustrative-clothing-rack.jpg',
  'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png'
);

UPDATE happy_gallery_entries
SET image_path = 'assets/uploads/gallery/giftly-illustrative-family-donations.png',
    caption = 'Families and volunteers sorting donated clothes',
    category_id = (SELECT id FROM gallery_categories WHERE slug = 'families'),
    location_safe = NULL,
    consent_confirmed = 1,
    guardian_consent = 1,
    is_illustrative = 1,
    moderation_status = 'approved',
    is_published = 1,
    reviewed_by = 1,
    reviewed_at = NOW(),
    published_at = COALESCE(published_at, NOW())
WHERE image_path IN (
  'assets/uploads/gallery/giftly-illustrative-denim.webp',
  'assets/uploads/gallery/giftly-illustrative-family-donations.png'
);

UPDATE happy_gallery_entries
SET caption = 'Community members exchanging donated clothing',
    location_safe = NULL,
    consent_confirmed = 1,
    guardian_consent = 0,
    is_illustrative = 1,
    moderation_status = 'approved',
    is_published = 1,
    reviewed_by = 1,
    reviewed_at = NOW()
WHERE image_path = 'assets/uploads/gallery/giftly-illustrative-community-donations.png';

INSERT INTO happy_gallery_entries (
  charity_id, submitted_by, campaign_id, category_id, image_path, caption, location_safe,
  consent_confirmed, guardian_consent, is_illustrative, moderation_status, is_published,
  reviewed_by, reviewed_at, published_at
)
SELECT
  cr.charity_id,
  NULL,
  NULL,
  gc.id,
  'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png',
  'Volunteers handing over clothing donations',
  NULL,
  1,
  0,
  1,
  'approved',
  1,
  1,
  NOW(),
  NOW()
FROM clothing_requests cr
JOIN gallery_categories gc ON gc.slug = 'community'
WHERE cr.id = 1
  AND NOT EXISTS (
    SELECT 1 FROM happy_gallery_entries g
    WHERE g.image_path = 'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png'
  );

INSERT INTO happy_gallery_entries (
  charity_id, submitted_by, campaign_id, category_id, image_path, caption, location_safe,
  consent_confirmed, guardian_consent, is_illustrative, moderation_status, is_published,
  reviewed_by, reviewed_at, published_at
)
SELECT
  cr.charity_id,
  NULL,
  NULL,
  gc.id,
  'assets/uploads/gallery/giftly-illustrative-family-donations.png',
  'Families and volunteers sorting donated clothes',
  NULL,
  1,
  1,
  1,
  'approved',
  1,
  1,
  NOW(),
  NOW()
FROM clothing_requests cr
JOIN gallery_categories gc ON gc.slug = 'families'
WHERE cr.id = 2
  AND NOT EXISTS (
    SELECT 1 FROM happy_gallery_entries g
    WHERE g.image_path = 'assets/uploads/gallery/giftly-illustrative-family-donations.png'
  );

INSERT INTO happy_gallery_entries (
  charity_id, submitted_by, campaign_id, category_id, image_path, caption, location_safe,
  consent_confirmed, guardian_consent, is_illustrative, moderation_status, is_published,
  reviewed_by, reviewed_at, published_at
)
SELECT
  cr.charity_id,
  NULL,
  NULL,
  gc.id,
  'assets/uploads/gallery/giftly-illustrative-community-donations.png',
  'Community members exchanging donated clothing',
  NULL,
  1,
  0,
  1,
  'approved',
  1,
  1,
  NOW(),
  NOW()
FROM clothing_requests cr
JOIN gallery_categories gc ON gc.slug = 'community'
WHERE cr.id = 1
  AND NOT EXISTS (
    SELECT 1 FROM happy_gallery_entries g
    WHERE g.image_path = 'assets/uploads/gallery/giftly-illustrative-community-donations.png'
  );

INSERT INTO happy_gallery_consents (gallery_entry_id, confirmed_by, consent_type, statement_version)
SELECT g.id, 1, 'pictured_people', '2026-01'
FROM happy_gallery_entries g
WHERE g.image_path IN (
  'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png',
  'assets/uploads/gallery/giftly-illustrative-family-donations.png',
  'assets/uploads/gallery/giftly-illustrative-community-donations.png'
)
  AND NOT EXISTS (
    SELECT 1 FROM happy_gallery_consents c
    WHERE c.gallery_entry_id = g.id AND c.consent_type = 'pictured_people'
  );

INSERT INTO happy_gallery_consents (gallery_entry_id, confirmed_by, consent_type, statement_version)
SELECT g.id, 1, 'guardian_for_child', '2026-01'
FROM happy_gallery_entries g
WHERE g.image_path = 'assets/uploads/gallery/giftly-illustrative-family-donations.png'
  AND NOT EXISTS (
    SELECT 1 FROM happy_gallery_consents c
    WHERE c.gallery_entry_id = g.id AND c.consent_type = 'guardian_for_child'
  );

INSERT INTO happy_gallery_consents (gallery_entry_id, confirmed_by, consent_type, statement_version)
SELECT g.id, 1, 'child_safety_attestation', '2026-01'
FROM happy_gallery_entries g
WHERE g.image_path IN (
  'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png',
  'assets/uploads/gallery/giftly-illustrative-family-donations.png',
  'assets/uploads/gallery/giftly-illustrative-community-donations.png'
)
  AND NOT EXISTS (
    SELECT 1 FROM happy_gallery_consents c
    WHERE c.gallery_entry_id = g.id AND c.consent_type = 'child_safety_attestation'
  );

INSERT INTO content_moderation_log (gallery_entry_id, moderator_id, action, note)
SELECT
  g.id,
  1,
  'approved',
  'Illustrative photo supplied by Giftly; usage rights and model releases confirmed. Guardian consent recorded where a child is pictured. Not a verified Giftly outcome.'
FROM happy_gallery_entries g
WHERE g.is_illustrative = 1
  AND g.image_path IN (
    'assets/uploads/gallery/giftly-illustrative-clothing-handoff.png',
    'assets/uploads/gallery/giftly-illustrative-family-donations.png',
    'assets/uploads/gallery/giftly-illustrative-community-donations.png'
  )
  AND NOT EXISTS (
    SELECT 1 FROM content_moderation_log l
    WHERE l.gallery_entry_id = g.id
      AND l.action = 'approved'
      AND l.note = 'Illustrative photo supplied by Giftly; usage rights and model releases confirmed. Guardian consent recorded where a child is pictured. Not a verified Giftly outcome.'
  );
