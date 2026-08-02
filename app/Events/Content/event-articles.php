<?php

declare(strict_types=1);

/**
 * Event article catalog — the static content for the /events/journal feed.
 *
 * 32 articles grouped into 8 categories. The events table is empty today, so
 * the body copy and event names are drafted from what each image actually
 * shows (no fabricated dates, attendees, or specifics). Replace this with a
 * backed repository when the admin surface lands.
 *
 * Each entry: slug, category, category_label, eyebrow, title, excerpt,
 * image (path under /storage/cms-media/), image_alt, body (3 paragraphs).
 */

return [

    // ─────────────────────────────────────────────────────────────────────
    // 1. Festivals
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "girija-kalyana",
        "category" => "festivals",
        "category_label" => "Festivals",
        "eyebrow" => "Festival",
        "title" => "Girija Kalyana",
        "excerpt" => "The celebration of the divine union of Shiva and Parvati, observed as a community festival at the temple.",
        "image" => "/storage/cms-media/journal-festivals-01-girija-kalyana.webp",
        "image_alt" => "Banner of the Girija Kalyana Samithi, Mysore, with illustrations of deities and a temple.",
        "body" => [
            "Girija Kalyana marks the celestial wedding of Goddess Parvati and Lord Shiva. The banner carried into the temple hall carries the names of the families and organisations that have come together to host the celebration, alongside illustrations of the deities and the temple that hosts the event.",
            "A festival of this scale is a logistical and devotional undertaking. Modaks and other sweets are prepared, the hall is dressed in marigold and jasmine, and the priest leads the wedding ritual at the centre of the gathering. Disciples of the guru and members of the surrounding community attend together.",
            "The festival strengthens the link between the temple and the families that surround it. It is one of the more visible annual occasions at which the trust’s work — feeding, hosting, and accommodating the visitors — is on full display.",
        ],
    ],
    [
        "slug" => "ganesh-chaturthi",
        "category" => "festivals",
        "category_label" => "Festivals",
        "eyebrow" => "Festival",
        "title" => "Ganesh Chaturthi",
        "excerpt" => "The festival of new beginnings, observed at the temple with an installed Ganesh murti and a full ten-day schedule.",
        "image" => "/storage/cms-media/journal-festivals-03-ganesh-chaturthi.webp",
        "image_alt" => "Devotees gathered before a flower-decorated Ganesh murti at a Ganesh Chaturthi celebration.",
        "body" => [
            "Ganesh Chaturthi is the festival of new beginnings. The temple installed a Ganesh murti for the duration of the festival, dressed in flowers, with a brass deepam at the foot of the throne and a torana of white jasmine hanging above.",
            "The celebration drew a community that is not always at the temple on ordinary days. Lawyers in formal suits, families with children, and elders all stood in a line for darshan, many holding phones aloft to record the moment. The formality of dress was matched by the simplicity of the ritual.",
            "The festival is also a logistical exercise for the trust — the daily pooja, the abhishekam, the annadanam that follows, and the eventual visarjan. Each of these is funded by the donations that keep the temple’s calendar running.",
        ],
    ],
    [
        "slug" => "brahmotsavam",
        "category" => "festivals",
        "category_label" => "Festivals",
        "eyebrow" => "Annual",
        "title" => "Brahmotsavam",
        "excerpt" => "The annual festival of the temple — processions, honours, song, and the community gathered for ten days.",
        "image" => "/storage/cms-media/events-slide-8-brahmotsavam.webp",
        "image_alt" => "Four-panel collage of a Brahmotsavam festival: processions, award presentations, and group photos.",
        "body" => [
            "Brahmotsavam is the annual festival of the temple — ten days of processions, honours, classical performance, and shared meals. The collage captures some of the festival’s many settings: dignitaries on stage, an award presentation, a classical performance, and a large group photograph of the participants.",
            "The festival is when the trust’s year-round work becomes most visible. The kitchen runs for longer hours, the halls are dressed daily, and the volunteers — some of them members of the surrounding villages — take on the work of hosting the visitors.",
            "Brahmotsavam is also an opportunity for the trust to recognise the people who sustain it. The award presentations and group photographs are not ceremonial filler; they are how the trust says thank you to the teachers, organisers, and donors who make the year possible.",
        ],
    ],
    [
        "slug" => "varalakshmi-vratam",
        "category" => "festivals",
        "category_label" => "Festivals",
        "eyebrow" => "Festival",
        "title" => "Varalakshmi Vratam",
        "excerpt" => "The worship of abundance, observed by the women of the community with a full ritual at the temple.",
        "image" => "/storage/cms-media/events-slide-2-varalakshmi.webp",
        "image_alt" => "Priest performing puja before a flower-adorned Lakshmi murti at Varalakshmi Vratam.",
        "body" => [
            "Varalakshmi Vratam is a women-led festival in honour of Goddess Lakshmi. The women of the community gather at the temple for the full ritual, with the priest presiding over the puja before a heavily decorated Lakshmi murti.",
            "The visual richness of the occasion is part of the offering. The murti is dressed in fresh flowers — roses, jasmine, chrysanthemum — with banana leaves forming a green frame on either side. Brass deepams burn at the foot of the throne. Offerings — fruit, rice, kumkum — are arranged on silver platters on the floor.",
            "The festival is one of the more labour-intensive days on the temple’s calendar, and a clear expression of the women’s role in the temple’s rhythm. The trust supports the festival with the flower arrangements, the catering, and the spaces that hold the gathering.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 2. Cultural Evenings / Dance
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "bharatanatyam-vrinda-samsthanam",
        "category" => "cultural",
        "category_label" => "Cultural Evenings",
        "eyebrow" => "Performance",
        "title" => "Bharatanatyam at Vrinda Samsthanam",
        "excerpt" => "A group Bharatanatyam recital offered in devotion by disciples of the guru at the temple hall.",
        "image" => "/storage/cms-media/events-slide-7-cultural-evenings.webp",
        "image_alt" => "Five Bharatanatyam dancers seated on stage in matching green and gold costumes, in front of the Vrinda Samsthanam banner.",
        "body" => [
            "Five young dancers took the stage in matching green and gold Bharatanatyam costumes, with ornate headpieces, traditional jewellery, and red mehndi on their hands. They were disciples of the same guru, performing together as a group.",
            "The hall was dressed for the occasion: a deep blue curtain backdrop, marigold garlands framing the stage, and the Vrinda Samsthanam banner at the centre. Audio equipment at the side of the stage carried the nattuvangam and the song to the back of the hall.",
            "Cultural evenings like this are part of the temple’s rhythm beyond the strictly ritual calendar. The trust provides the hall, the staging, and the support that lets the disciples prepare and perform; the offering itself is theirs.",
        ],
    ],
    [
        "slug" => "bharatanatyam-tribute",
        "category" => "cultural",
        "category_label" => "Cultural Evenings",
        "eyebrow" => "Performance",
        "title" => "Solo Bharatanatyam — a guru tribute",
        "excerpt" => "A single dancer’s anjali mudra opens a classical performance dedicated to the guru.",
        "image" => "/storage/cms-media/journal-cultural-01-bharatanatyam-tribute.webp",
        "image_alt" => "Solo Bharatanatyam dancer in anjali mudra, wearing a green and gold costume, performing on a red-carpeted stage.",
        "body" => [
            "A young dancer opens the recital in anjali mudra — palms pressed together above the head, eyes closed, the body still. It is the gesture that begins and ends a classical Bharatanatyam performance, and a mark of the offering being made to the guru.",
            "The dancer wears the traditional green and gold costume with a pleated fan at the front, gold jewellery at the waist and ears, and a flower in the hair. The carved temple-style pillar at the side of the stage and the marigold garland overhead are part of the temple’s dressing for the evening.",
            "Solo recitals are a more intimate format than the group performance, and they let the discipline of the individual dancer come through. The trust hosts several of these each year, often as part of a longer programme tied to a particular occasion.",
        ],
    ],
    [
        "slug" => "bharatanatyam-guru-shishya",
        "category" => "cultural",
        "category_label" => "Cultural Evenings",
        "eyebrow" => "Performance",
        "title" => "Bharatanatyam — Guru Shishya Parampara",
        "excerpt" => "A group performance staged under the Guru Shishya banner, continuing the parampara of teacher and student.",
        "image" => "/storage/cms-media/journal-cultural-02-bharatanatyam-guru.webp",
        "image_alt" => "Bharatanatyam dancers in green and gold costumes, on stage in front of the Guru Shishya banner with the Indian map garland.",
        "body" => [
            "The Guru Shishya parampara — the lineage of teacher and student — is the throughline of this performance. The banner at the back of the stage is dedicated to it, flanked by a garland shaped like the map of India and a portrait of the guru.",
            "The dancers in green and gold line up at the front of the stage in the opening pose, with brass deepams at the foot of the platform. Marigold and orange garlands frame the stage edges, and the carved wooden pillars on either side are a permanent feature of the hall.",
            "The performance is part of a longer programme that the trust has supported over many seasons. The continuity of the parampara is the point — each year’s dancers are the next year’s teachers, and the temple is the place where the chain is held.",
        ],
    ],
    [
        "slug" => "bharatanatyam-hyderabad",
        "category" => "cultural",
        "category_label" => "Cultural Evenings",
        "eyebrow" => "Performance",
        "title" => "Bharatanatyam in Hyderabad",
        "excerpt" => "A Bharatanatyam recital staged in Hyderabad under the Viswa Guru Sri Ram Shishya Vrinda Samsthana banner.",
        "image" => "/storage/cms-media/journal-cultural-03-bharatanatyam-hyderabad.webp",
        "image_alt" => "Bharatanatyam dancers seated on a red-carpeted stage in matching green and gold costumes, with the Viswa Guru Sri Ram Shishya Vrinda Samsthana banner behind them.",
        "body" => [
            "The dancers are seated on the red-carpeted stage in a kneeling pose, hands raised in the opening mudra. The five of them are dressed in matching green and gold costumes with the traditional pleated fan at the front, gold jewellery, and red mehndi on the hands.",
            "The backdrop is the Viswa Guru Sri Ram Shishya Vrinda Samsthana banner, with the text in both Telugu and English. The marigold garland running along the top of the backdrop and the carved wooden pillars on either side are a familiar dressing for the trust’s cultural evenings.",
            "Staging the recital in Hyderabad means the work is reaching the wider community of disciples beyond the home temple. The trust works with local organisers to provide the staging, the sound, and the support that the dancers need on the road.",
        ],
    ],
    [
        "slug" => "literary-tribute-gurajada",
        "category" => "cultural",
        "category_label" => "Cultural Evenings",
        "eyebrow" => "Tribute",
        "title" => "Literary tribute to Gurajada",
        "excerpt" => "A literary and cultural evening honouring the Telugu poet Gurajada, with participants on stage in traditional dress.",
        "image" => "/storage/cms-media/journal-cultural-04-literary-tribute.webp",
        "image_alt" => "Five people in traditional white attire posing on a flower-decorated stage in front of a Telugu and English banner honouring the poet Gurajada.",
        "body" => [
            "The evening is in honour of Gurajada, the Telugu poet whose work is foundational to modern Telugu literature. The participants — five of them, in white traditional dress — are on stage in front of a bilingual Telugu and English banner, with marigold garlands dressing the edges of the platform.",
            "A literary tribute is a different kind of event from a ritual one. The audience is smaller, the speeches longer, and the format closer to a recitation than a pooja. But the trust hosts them for the same reason it hosts the dance evenings — they are part of the cultural life the temple sustains.",
            "Events like this also keep the hall in use across the year, beyond the festival calendar. The community knows the temple as a place where these things happen, not only as a place where pujas are performed.",
        ],
    ],
    [
        "slug" => "sangam-singing-and-dance",
        "category" => "cultural",
        "category_label" => "Cultural Evenings",
        "eyebrow" => "Sangam",
        "title" => "Guru Sriram Shishya Sangam",
        "excerpt" => "A four-panel record of a Sangam evening: devotional singing, classical dance, and a deity on stage.",
        "image" => "/storage/cms-media/journal-cultural-05-sangam-collage.webp",
        "image_alt" => "Four-panel collage of a Guru Sriram Shishya Sangam evening: devotional singing, dance, and a decorated deity on stage.",
        "body" => [
            "The Sangam — a gathering of disciples — brings several of the trust’s cultural formats into one evening. The four panels of the collage show the range: a man at the microphone for devotional singing, a group of classical dancers in matching costumes, a decorated deity on stage, and a man in a purple and gold traditional outfit seated beside another speaker.",
            "The banner of the Guru Sriram Shishya Sangam runs through each panel. It is the same banner that has appeared at the dance recitals and the literary tributes, because the Sangam is the umbrella under which all of these programmes are held.",
            "Putting them into one evening also gives the trust an economy of scale — the same hall, the same staging, the same volunteers, but a programme that fills the whole night. It is one of the more efficient ways the trust spends its hosting budget.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 3. Daily Pooja / Sacred Rituals
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "sandhyavandanam",
        "category" => "pooja",
        "category_label" => "Daily Pooja",
        "eyebrow" => "Twilight pooja",
        "title" => "Sandhyavandanam",
        "excerpt" => "The twilight pooja at the sanctum — the lamp lit, the prayer offered, the day handed back.",
        "image" => "/storage/cms-media/journal-pooja-01-sandhyavandanam.webp",
        "image_alt" => "Priest performing Sandhyavandanam before a flower-adorned goddess in the sanctum.",
        "body" => [
            "Sandhyavandanam is the twilight pooja, performed at the close of the day. The priest stands before the decorated murti in the sanctum, with a brass deepam lit at the foot of the throne and the offerings arranged on the floor.",
            "The murti is dressed for the evening in fresh flowers — roses, chrysanthemum, tuberose — with banana leaves forming a green frame on either side. The white torana of jasmine flowers hangs above. The hall is lit by the deepam and the overhead tube lights; the rest of the temple is settling into the evening.",
            "Sandhyavandanam is one of the rituals the trust supports on a daily basis. It is not a festival — no banners, no audience — but it is the rhythm that holds the temple together between the larger occasions.",
        ],
    ],
    [
        "slug" => "nivedanam",
        "category" => "pooja",
        "category_label" => "Daily Pooja",
        "eyebrow" => "Morning abhishekam",
        "title" => "Nivedanam",
        "excerpt" => "The morning abhishekam and the offering that follows — the day’s first pooja, performed at sunrise.",
        "image" => "/storage/cms-media/journal-pooja-02-nivedanam.webp",
        "image_alt" => "Priest performing the morning abhishekam before a flower-adorned murti in the sanctum.",
        "body" => [
            "Nivedanam is the morning abhishekam — the first pooja of the day, performed at sunrise. The priest stands barefoot before the murti, the brass kalasham and the offerings arranged on the small stools at the foot of the throne.",
            "The sanctum is dressed for the morning: a heavy garland of marigold and roses around the murti, a white torana of jasmine flowers overhead, banana leaves on either side, and a brass deepam waiting to be lit. The priest’s movements are unhurried — the abhishekam takes the time it takes.",
            "The morning pooja is the anchor of the temple’s day. Everything else — the office work, the kitchen prep, the visitors, the larger rituals — is built around it. The trust’s job is to make sure the sanctum is ready and the priest is supported, every morning, without fail.",
        ],
    ],
    [
        "slug" => "ritual-procession",
        "category" => "pooja",
        "category_label" => "Daily Pooja",
        "eyebrow" => "Procession",
        "title" => "Outdoor ritual procession",
        "excerpt" => "A procession that takes the ritual out of the sanctum and into the open air, with leaf-decorated poles carried through the gathering.",
        "image" => "/storage/cms-media/journal-pooja-03-procession.webp",
        "image_alt" => "A crowd carrying leaf-decorated poles and a framed image in an outdoor ritual procession.",
        "body" => [
            "Not every ritual is performed inside the sanctum. This procession took the pooja out into the open air, with several leaf-decorated poles carried through the gathering. A framed image was part of the procession, lifted up so the crowd could see it.",
            "The participants are a mix of men in white dhotis, women in coloured saris, and children held up to see. The setting is rural, with green trees visible behind the crowd. The ground underfoot is dirt and sand — the procession has come out of the temple hall into the village.",
            "Outdoor processions are part of how a temple stays connected to the surrounding community. They are also the most logistically demanding events the trust supports, because the ritual has to be performed in motion, in weather, and in the presence of a much larger crowd than the sanctum can hold.",
        ],
    ],
    [
        "slug" => "sacred-wooden-post",
        "category" => "pooja",
        "category_label" => "Daily Pooja",
        "eyebrow" => "Sacred object",
        "title" => "The sacred wooden post",
        "excerpt" => "A crowd gathered reverently around a freshly cut wooden post, with peepal leaves laid on top — a moment of community veneration.",
        "image" => "/storage/cms-media/journal-pooja-04-sacred-post.webp",
        "image_alt" => "A crowd gathered around a freshly cut wooden post with peepal leaves laid on top, with people reaching out to touch the wood.",
        "body" => [
            "A thick, freshly cut wooden post stands at the centre of the gathering, with peepal leaves laid across its top surface. People around the post are reaching out to touch it — women in saris, men in casual shirts, children, all holding a hand against the wood for a moment.",
            "A peepal leaf on a freshly cut post is a familiar signal of a tree being marked as sacred — either being installed in a particular place, or being venerated before it is shaped into something. The act of touching the post is a way of taking part in the moment without the ritual being formally open.",
            "The trust does not always organise these gatherings, but it supports them when the occasion is in or near the temple grounds. The cost is small — the labour of the volunteers, the materials for the offering — and the meaning for the community is large.",
        ],
    ],
    [
        "slug" => "temple-entrance-decorated",
        "category" => "pooja",
        "category_label" => "Daily Pooja",
        "eyebrow" => "Temple",
        "title" => "The decorated temple entrance",
        "excerpt" => "A temple entrance dressed in scalloped fabric, mango leaves, and a small crowd gathered for the occasion — in the Odia region.",
        "image" => "/storage/cms-media/journal-pooja-05-temple-entrance.webp",
        "image_alt" => "A decorated temple entrance with scalloped fabric and mango leaves, with a small crowd of men gathered in front.",
        "body" => [
            "The temple entrance has been dressed for the occasion: scalloped fabric in yellow, green, and white running along the edges, mango leaves laid against the doorframe, and a small crowd of men standing in front. The signboard in the background is in the Odia script.",
            "Decorating an entrance is one of the smaller acts of seva, but it sets the tone for what is happening inside. The volunteers who put up the fabric and the leaves are doing the same work as the priest inside the sanctum, just at a different scale.",
            "The trust works with several smaller temples and shrines in the region, and the dressed entrance is often how the visit begins. The temple trusts the work; the volunteers trust the temple; the visitors trust the day.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 4. Kalyanam (Weddings)
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "kalyanam-ceremony",
        "category" => "kalyanam",
        "category_label" => "Kalyanam",
        "eyebrow" => "Wedding",
        "title" => "Kalyanam at the temple",
        "excerpt" => "A wedding held at the temple, with the groom in a dark suit, the priest presiding, and the family gathered for the ritual.",
        "image" => "/storage/cms-media/journal-kalyanam-01.webp",
        "image_alt" => "A wedding ceremony at the temple, with the groom in a dark suit standing beside the priest who is performing rituals.",
        "body" => [
            "The groom stands in a dark suit on the right side of the frame, hands folded in a prayerful namaskar. The priest — in a cream and white traditional outfit with a yellow shawl over one shoulder — is at the centre, holding a plate with offerings. The sanctum is just behind, dressed in flowers and banana leaves for the occasion.",
            "A temple wedding is a smaller affair than a hall wedding, but it is the more traditional choice for families who want the ritual held in front of the deity. The priest performs the kalyanam at the foot of the sanctum, with the family standing close by and the photographers at the edges.",
            "The trust supports temple weddings as part of the regular calendar. The hall is dressed, the priest is on hand, the offerings are prepared, and the family’s day is taken care of around the rituals themselves.",
        ],
    ],
    [
        "slug" => "kalyanam-red-saree",
        "category" => "kalyanam",
        "category_label" => "Kalyanam",
        "eyebrow" => "Wedding",
        "title" => "A kalyanam with a brass deepam",
        "excerpt" => "A South Indian wedding ritual in which the bride, in a red silk saree, stands beside a brass deepam at the centre of the hall.",
        "image" => "/storage/cms-media/journal-kalyanam-02.webp",
        "image_alt" => "A South Indian kalyanam with a woman in a red silk saree standing beside a brass deepam, with men in white attire around her.",
        "body" => [
            "The bride wears a red silk saree with a green and gold border, dressed for the central part of the kalyanam. The brass deepam at her side is held aloft, and the men in white traditional attire stand around her in a half-circle. The hall is dressed in marigold and orange drapes.",
            "A South Indian wedding is several rituals over several hours, and the brass deepam marks the formal moment — the part of the ceremony when the offerings are made in the presence of the assembled family. The trust has supported hundreds of these in its halls.",
            "The weddings that happen at the temple are a steady, year-round part of the trust’s work. They are also the events at which the trust meets the widest range of families — every social and economic background, all brought to the same sanctum.",
        ],
    ],
    [
        "slug" => "kalyanam-with-kalasham",
        "category" => "kalyanam",
        "category_label" => "Kalyanam",
        "eyebrow" => "Wedding",
        "title" => "A kalyanam with the kalasham",
        "excerpt" => "A wedding ritual performed around the kalasham — the decorated pot that marks the centre of the South Indian wedding.",
        "image" => "/storage/cms-media/journal-kalyanam-03.webp",
        "image_alt" => "A wedding ritual with the bride in a pink silk saree and a decorated kalasham at the centre of the gathering.",
        "body" => [
            "The kalasham — a pot wrapped in gold and red foil and dressed in yellow and pink flower garlands — stands at the centre of the gathering. The bride wears a pink silk saree with a heavy gold border, the groom a cream silk dhoti. The priest is making the offerings.",
            "The kalasham is the symbolic centre of a South Indian wedding. It represents the cosmic pot, the source of life and abundance, and the rituals performed around it are the heart of the ceremony. The priest’s movements are precise, the family’s attention is on the kalasham, and the rest of the hall is the audience.",
            "The trust’s role in a wedding is practical as much as devotional. The hall is booked, the priest is paid, the flowers are sourced, the kalasham is prepared, and the kitchen is on hand for the meal that follows. The family’s day is the centrepiece; the trust’s work is everything around it.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 5. Kumbhabhishekam
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "kumbhabhishekam",
        "category" => "kumbhabhishekam",
        "category_label" => "Kumbhabhishekam",
        "eyebrow" => "Consecration",
        "title" => "Kumbhabhishekam",
        "excerpt" => "The reconsecration of the temple — the moment the sanctum is renewed, and the temple is born again.",
        "image" => "/storage/cms-media/journal-kumbhabhishekam-01.webp",
        "image_alt" => "A Kumbhabhishekam ceremony with formally dressed devotees in prayer before a flower-decorated murti.",
        "body" => [
            "A Kumbhabhishekam is the consecration of a temple — the formal moment at which the sanctum is renewed and the deity is reinstalled in the presence of the community. The hall is dressed for the occasion, the murtis are decorated in fresh flowers, and the assembled devotees stand with hands folded.",
            "The men in the foreground are in formal black suits, and the priest stands in a white and yellow traditional outfit at the centre. The contrast in dress — suits and dhotis, formal and traditional — is part of what a Kumbhabhishekam is: the temple meets the wider community at its most formal.",
            "A Kumbhabhishekam is a once-in-a-generation event for any temple. The trust has supported one in the recent past, and the photographs from the day are part of how the temple remembers it. The next one is a long way off, and the day’s work is what will carry the temple to it.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 6. Award / Felicitation
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "chaganti-koteswara-rao",
        "category" => "awards",
        "category_label" => "Awards & Felicitation",
        "eyebrow" => "Felicitation",
        "title" => "Chaganti Koteswara Rao Shasti",
        "excerpt" => "A Shasti celebration for the Telugu spiritual orator Chaganti Koteswara Rao, with five young participants on stage in traditional dress.",
        "image" => "/storage/cms-media/journal-awards-01-chaganti.webp",
        "image_alt" => "Five young boys in white traditional dress on stage with rosette badges, in front of a Chaganti Koteswara Rao banner.",
        "body" => [
            "Five young participants stand on the stage in matching white traditional dress, each wearing a yellow and blue rosette badge on the chest. The banner behind them is dedicated to the Shasti — the 60th year — of Chaganti Koteswara Rao, the Telugu spiritual orator, and the occasion is a felicitation in his honour.",
            "The participants look like they are part of a competition or a school event, and the rosettes are the marker. The banner features an illustration of the Tirumala temple and a small portrait of Chaganti Koteswara Rao, and it makes the connection between the young participants and the lineage of the orator they are honouring.",
            "The trust has hosted several of these felicitation evenings over the years, in collaboration with the families and organisations that organise them. The hall and the staging are the trust’s contribution; the celebration itself is the community’s.",
        ],
    ],
    [
        "slug" => "vivekananda-yuvan-balaga",
        "category" => "awards",
        "category_label" => "Awards & Felicitation",
        "eyebrow" => "Award",
        "title" => "Swami Vivekananda Yuvan Balaga",
        "excerpt" => "A group felicitation under the Swami Vivekananda Yuvan Balaga banner, with awardees and dignitaries on stage.",
        "image" => "/storage/cms-media/journal-awards-02-vivekananda.webp",
        "image_alt" => "A group award ceremony under the Swami Vivekananda Yuvan Balaga banner, with awardees and dignitaries on stage.",
        "body" => [
            "A large group stands on the red-carpeted stage in front of the Swami Vivekananda Yuvan Balaga banner. The awardees wear blue and yellow rosette badges, and the chief guest in the centre wears a cream-coloured traditional outfit with a yellow sash.",
            "The Yuvan Balaga is the youth wing of the Vrinda Sansthan, and the banner carries the Swami Vivekananda portrait in the corner and an illustration of a temple gopuram. The awardees are the young men and boys who have been recognised for some form of service or achievement during the year.",
            "Felicitation ceremonies are the trust’s way of saying that the work being done by the community is seen and acknowledged. The hall and the staging are provided, the chief guest is invited, and the families come together for the photograph and the meal that follow.",
        ],
    ],
    [
        "slug" => "shishya-celebration",
        "category" => "awards",
        "category_label" => "Awards & Felicitation",
        "eyebrow" => "Celebration",
        "title" => "Guru Shishya Celebration",
        "excerpt" => "A four-panel record of a Guru Shishya celebration: dignitaries, a sacred idol, classical dancers, and a chief guest in traditional dress.",
        "image" => "/storage/cms-media/journal-awards-03-shishya-celebration.webp",
        "image_alt" => "Four-panel collage of a Guru Shishya celebration with dignitaries, a sacred idol, classical dancers, and a chief guest in traditional dress.",
        "body" => [
            "The four panels of the collage capture a Guru Shishya celebration from several angles. The top-left shows dignitaries on stage. The top-right shows a sacred idol draped in silk, presented in the centre of the hall. The bottom-left shows a chief guest in a purple and gold traditional outfit. The bottom-right shows the classical dancers in matching green and gold costumes.",
            "A Guru Shishya celebration brings together the cultural and the ceremonial — the dance performance, the honouring of the guru, the felicitation of the chief guest, and the presentation of the sacred object. It is one of the more elaborate events the trust hosts.",
            "The trust’s role is to keep the hall running across the full day — the morning rehearsal, the afternoon’s performance, the evening’s felicitation — and to make sure the staging, the sound, and the catering are all in place. The celebration is the community’s; the running of it is the trust’s.",
        ],
    ],
    [
        "slug" => "sarvabhouma-event",
        "category" => "awards",
        "category_label" => "Awards & Felicitation",
        "eyebrow" => "Felicitation",
        "title" => "Guru Sarvabhouma Vishwajyothi",
        "excerpt" => "Six men in white traditional attire on stage with rosette badges, in front of the Guru Sarvabhouma Vishwajyothi banner from Mysuru.",
        "image" => "/storage/cms-media/journal-awards-04-sarvabhouma.webp",
        "image_alt" => "Six men in white traditional attire on stage with rosette badges, in front of the Guru Sarvabhouma Vishwajyothi banner from Mysuru.",
        "body" => [
            "Six men stand in a row on the stage in matching white traditional dress, with three of them wearing blue and yellow rosette badges. The central figure wears a red-bordered sash over his shoulder and a tilak on his forehead, marking him as the chief guest of the evening.",
            "The banner is in Kannada and English, with the text Shri Gurusrabhouma Vishwajyothi and an illustration of a temple gopuram. The temple gopuram is a familiar signal in the trust’s events — the connection between the hall and the sanctum is always being made visually.",
            "The evening is a smaller felicitation than some of the larger gatherings, but it follows the same shape: the honoured guests on stage, the rosettes marking the awardees, the banner making the occasion explicit, the photograph at the end.",
        ],
    ],
    [
        "slug" => "south-indian-award",
        "category" => "awards",
        "category_label" => "Awards & Felicitation",
        "eyebrow" => "Award",
        "title" => "A South Indian award ceremony",
        "excerpt" => "A formal award presentation on a stage dressed in marigold garlands, with the recipients and their families in the front row.",
        "image" => "/storage/cms-media/journal-awards-05-south-indian.webp",
        "image_alt" => "A formal award presentation on a stage dressed in marigold garlands, with the recipients and their families in the front row.",
        "body" => [
            "A group of men in white shirts are seated and standing on the stage, with two women in pink-and-green and purple silk sarees among them. The recipients are wearing garlands and holding commemorative plaques or shields, and a marigold garland runs along the top of the banner behind them.",
            "The banner is in the Kannada script, with a framed image at the foot of the platform. The language of the banner and the dress of the recipients make the regional context clear — this is a South Indian award, in the South Indian style, with the marigold and the formal dress that go with it.",
            "The trust hosts several of these each year, often as part of a larger programme. The plaques are presented, the recipients give short speeches, the photograph is taken, and the meal is served. It is a well-understood shape, and the trust has refined the logistics over many years.",
        ],
    ],
    [
        "slug" => "ganap-sachidananda",
        "category" => "awards",
        "category_label" => "Awards & Felicitation",
        "eyebrow" => "Gathering",
        "title" => "Sri Ganap Sachidananda",
        "excerpt" => "A group portrait of the gathering around the Sri Ganap Sachidananda tradition, in front of the guru’s banner.",
        "image" => "/storage/cms-media/journal-awards-06-ganap-sachidananda.webp",
        "image_alt" => "A group portrait of the gathering around the Sri Ganap Sachidananda tradition, in front of the guru’s banner.",
        "body" => [
            "The group stands on the stage in two rows, with the men in the back row taller and the boys in the front row shorter. Most of them are in white traditional dress, with several wearing rosette badges. The seated figure at the centre wears a cream-coloured veshti with a maroon border and holds a small floral item.",
            "The banner behind them is in Telugu, with the text Guru Spram Shishya Vrinda Samsthanami and references to Sri Ganap Sachidananda. The small portrait at the top right of the banner is of the guru, and the temple gopuram is at the left.",
            "Gatherings like this are a regular feature of the trust’s calendar. They are part award ceremony, part collective photograph, part family reunion — the people who come to these evenings have been coming to them for years, and the photographs are part of how the trust keeps track of the community it serves.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 7. Community / Seva
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "village-group",
        "category" => "community",
        "category_label" => "Community & Seva",
        "eyebrow" => "Community",
        "title" => "A village group",
        "excerpt" => "A large group of villagers gathered together for a community photograph — a record of the families the temple serves.",
        "image" => "/storage/cms-media/journal-community-01-village-group.webp",
        "image_alt" => "A large group of villagers gathered together for a community photograph, in front of a building with a barred window.",
        "body" => [
            "A large group of villagers — men, women, and young adults — are gathered closely together in a posed photograph. They are in front of a building with cream walls and a barred window, with a red carpet or flooring visible at one side. Several of the older men wear white shirts and have tilak markings on their foreheads.",
            "The photograph is the kind of record that a temple keeps of the community it serves. The same families come to the temple for the festivals, the weddings, the daily poojas, and the smaller occasions in between. The photograph is a way of saying that the temple has seen them and remembers them.",
            "The trust’s relationship with the surrounding villages is the foundation of its work. The annadanam feeds the people who come to the hall, the festivals bring them in for the larger occasions, and the daily poojas are the rhythm that holds the whole thing together.",
        ],
    ],
    [
        "slug" => "ceremony-with-priest",
        "category" => "community",
        "category_label" => "Community & Seva",
        "eyebrow" => "Gathering",
        "title" => "A ceremony with a priest",
        "excerpt" => "A group of men and boys gathered around a priestly figure in a white garment — a community ritual in an everyday setting.",
        "image" => "/storage/cms-media/journal-community-02-ceremony-with-priest.webp",
        "image_alt" => "A group of men and boys gathered around a priestly figure in a white garment, in an everyday room with cream walls.",
        "body" => [
            "The group has clustered together in an everyday room with cream walls and a blue baseboard, looking attentively toward something off-camera. The priestly figure in the foreground wears a white garment with a beige shawl, and an older man with a red tilak stands among the boys.",
            "The setting is not the temple hall — it is a smaller space, a community room or a classroom. The ritual is being held here because the community has asked for it to be held here, and the priest has come to be with them. The boys in the group are young, and the ritual is also part of how they are being brought into the tradition.",
            "The trust supports these off-temple rituals as well as the in-temple ones. The priest’s time, the materials for the offering, and the space itself are part of what the trust makes available. The community brings the gathering; the trust brings the structure.",
        ],
    ],
    [
        "slug" => "ashram-boys",
        "category" => "community",
        "category_label" => "Community & Seva",
        "eyebrow" => "Ashram",
        "title" => "The boys at the ashram",
        "excerpt" => "A group of young boys at the ashram, with a caretaker in a white garment — a record of the children the trust supports.",
        "image" => "/storage/cms-media/journal-community-03-ashram-boys.webp",
        "image_alt" => "A group of young boys at the ashram with a caretaker in a white garment, in front of a building with a barred window.",
        "body" => [
            "A group of fifteen to twenty young boys stands in a rough line in front of a building with a peach-coloured wall and a barred window. They are dressed in casual everyday clothes — striped t-shirts, shorts, simple shirts — and several of them have tilak markings on their foreheads. The man in the white traditional garment among them is the caretaker.",
            "The ashram is one of the trust’s longer-running commitments. The boys who live here are supported through their schooling, their meals, and the basic infrastructure of a place to live. The photograph is a record of a particular cohort — the children who were at the ashram on this particular day.",
            "The trust does not publicise the ashram’s work in the way it publicises a festival. The work is daily, the children are private, and the relationship is the thing that matters. The photograph is here because it is part of what the trust does, and the journal is a record of all of it.",
        ],
    ],
    [
        "slug" => "ashram-adults",
        "category" => "community",
        "category_label" => "Community & Seva",
        "eyebrow" => "Ashram",
        "title" => "The adults at the ashram",
        "excerpt" => "A group of boys and adults at the ashram, in front of a building with a blue door and a barred window — the wider community of the home.",
        "image" => "/storage/cms-media/journal-community-04-ashram-adults.webp",
        "image_alt" => "A group of boys and adults at the ashram, in front of a building with a blue door and a barred window.",
        "body" => [
            "A larger group of boys and adults is arranged in several rows in front of a building with a blue-painted step and a barred window. The adults stand at the back, the boys cluster in the middle, and a man in a cream kurta stands among them on the left.",
            "The adults in the photograph are the people who run the ashram day to day — the caretakers, the teachers, the visiting volunteers. The boys are the children who live there. The relationship between the two is the ashram’s reason for being.",
            "The ashram is a long-term project, and the photographs in the trust’s records are taken over many years. The adults change, the boys grow up, and the ashram continues. The journal keeps the photographs so that the continuity is visible.",
        ],
    ],
    [
        "slug" => "villagers-outdoors",
        "category" => "community",
        "category_label" => "Community & Seva",
        "eyebrow" => "Village",
        "title" => "Villagers gathered outdoors",
        "excerpt" => "A community group of villagers gathered outdoors in front of the local buildings — a record of the surrounding community.",
        "image" => "/storage/cms-media/journal-community-05-villagers.webp",
        "image_alt" => "A community group of villagers gathered outdoors in front of the local buildings, with palm fronds and posters on the walls.",
        "body" => [
            "A group of villagers is gathered outdoors in front of the local buildings — a peach-painted wall with a barred window, posters and notices attached to the wall, and palm fronds visible to the right. The dress is everyday and mixed — saris, shirts, a child in a bright turquoise top.",
            "This is the village as it is on an ordinary day, not a festival day. The trust’s work is most visible on festival days, but the daily poojas and the kitchen that runs every day of the year are the part of the work that the village depends on.",
            "The relationship between the trust and the surrounding villages is not a one-way thing. The villagers come to the temple for the rituals; the temple sends its priest and its volunteers to the village for the occasions that need them. The journal is a record of both directions of the relationship.",
        ],
    ],
    [
        "slug" => "religious-booklets",
        "category" => "community",
        "category_label" => "Community & Seva",
        "eyebrow" => "Distribution",
        "title" => "Distribution of religious booklets",
        "excerpt" => "A group of young men and boys outdoors holding religious booklets — a record of a community distribution day.",
        "image" => "/storage/cms-media/journal-community-06-booklets.webp",
        "image_alt" => "A group of young men and boys outdoors holding religious booklets, with a man in traditional dress among them.",
        "body" => [
            "A group of young men and boys is gathered outdoors on grass, with coconut palms and a yellow building visible behind them. Several of them are holding colourful booklets with religious imagery on the covers, and a man in a traditional white kurta stands among them at the back.",
            "The distribution of religious booklets is a smaller form of community work, but a regular one. The booklets carry the prayers, the calendar of festivals, and the teachings of the tradition, and the distribution is a way of putting them in the hands of the people who will use them.",
            "The trust supports the distribution with the printing, the transport, and the gathering. The young men and boys in the photograph are the recipients of the day — some of them will pass the booklets on to others in their families, and the cycle continues.",
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────
    // 8. Indoor Public Gatherings
    // ─────────────────────────────────────────────────────────────────────
    [
        "slug" => "indoor-gathering",
        "category" => "indoor",
        "category_label" => "Indoor Gatherings",
        "eyebrow" => "Indoor",
        "title" => "An indoor public gathering",
        "excerpt" => "A large indoor gathering with the front row of distinguished guests seated close together, and a larger audience in the rows behind.",
        "image" => "/storage/cms-media/journal-indoor-01.webp",
        "image_alt" => "A large indoor gathering with the front row of distinguished guests seated close together, and a larger audience in the rows behind.",
        "body" => [
            "The front row of the indoor gathering is a cluster of distinguished men — one in white traditional dress with a tilak, one in a maroon shirt, one in a light blue shirt with a wristwatch, and an older gentleman with white hair in a white shirt. The larger audience is visible in the rows behind them, seated in a packed hall.",
            "Indoor public gatherings of this size are a regular part of the trust’s work. The hall is configured with the front row of chairs reserved for the chief guest and the dignitaries, and the back rows for the community. The lighting is from the hall’s overhead fittings; the rest of the temple is closed for the duration of the event.",
            "The trust has refined the running of these evenings over many years. The sound, the seating, the entry and exit, the photograph, the meal — every part of the evening is rehearsed in the sense that the trust knows what works. The photograph is a record of one of these evenings.",
        ],
    ],
];
