DROP TABLE IF EXISTS `cms_pages`;

CREATE TABLE `cms_pages` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`slug` VARCHAR(255) NULL,
`title` VARCHAR(255) NULL,
`published` VARCHAR(255) NULL,
`last_modified` VARCHAR(255) NULL,
INDEX `idx_cms_pages_slug` (`slug`),
INDEX `idx_cms_pages_published` (`published`),
INDEX `idx_cms_pages_last_modified` (`last_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_pages` (`id`,`payload`,`slug`,`title`,`published`,`last_modified`) VALUES
('1','{\"id\":1,\"title\":\"Home\",\"slug\":\"home\",\"content\":\"<p>SparkCMS helps teams publish accessible websites faster with flexible blocks, guardrails, and analytics.</p><p>Explore the platform, meet the team, or start a project with our services and support.</p>\",\"views\":0,\"last_modified\":1751716800,\"published\":true,\"template\":\"page.php\",\"meta_title\":\"SparkCMS \\u2014 Build Faster, Accessible Websites\",\"meta_description\":\"Modern CMS for agencies and nonprofits. Design, publish, and optimize fast, accessible sites with built-in SEO, performance tools, and intuitive workflows.\",\"og_title\":\"Home\",\"og_description\":\"Welcome to the Home Page\",\"og_image\":\"\",\"access\":\"public\",\"canonical_url\":\"https://www.sparkcms.dev/\",\"robots\":\"index,follow\"}','home','Home','1','1751716800'),
('2','{\"id\":2,\"title\":\"About Us\",\"slug\":\"about-us\",\"content\":\"<p>We are a distributed team of designers, engineers, and content strategists building modern publishing tools.</p><p>Our mission is to help organizations deliver inclusive digital experiences without slowing down.</p>\",\"views\":0,\"last_modified\":1751803200,\"published\":true,\"template\":\"page.php\",\"meta_title\":\"About SparkCMS \\u2014 Our Mission, Team & Values\",\"meta_description\":\"Learn who we are, what we\\u2019re building, and how SparkCMS helps teams ship beautiful, accessible websites faster with reliable workflows and support.\",\"og_title\":\"About Us\",\"og_description\":\"Learn more about our company\",\"og_image\":\"\",\"access\":\"public\",\"canonical_url\":\"https://www.sparkcms.dev/about-us/\",\"robots\":\"index,follow\"}','about-us','About Us','1','1751803200'),
('3','{\"id\":3,\"title\":\"Services\",\"slug\":\"services\",\"content\":\"<p>We partner with teams on strategy, implementation, and long-term optimization.</p><ul><li>CMS setup and migrations</li><li>Custom themes and component libraries</li><li>Performance, accessibility, and SEO audits</li></ul>\",\"views\":0,\"last_modified\":1751889600,\"published\":true,\"template\":\"page.php\",\"meta_title\":\"Services \\u2014 SparkCMS Implementation, Design & Support\",\"meta_description\":\"From migrations to custom themes and performance tuning, SparkCMS services help you launch faster and keep your site secure, accessible, and SEO-ready.\",\"og_title\":\"Services\",\"og_description\":\"Overview of the services we offer\",\"og_image\":\"\",\"access\":\"public\",\"canonical_url\":\"https://www.sparkcms.dev/services/\",\"robots\":\"index,follow\"}','services','Services','1','1751889600'),
('4','{\"id\":4,\"title\":\"Blogs\",\"slug\":\"blogs\",\"content\":\"<p>Read product updates, editorial guidance, and technical deep dives from the SparkCMS team.</p>\",\"views\":0,\"last_modified\":1751976000,\"published\":true,\"template\":\"blog-list.php\",\"meta_title\":\"Blog \\u2014 SparkCMS Updates, Tutorials & Product News\",\"meta_description\":\"Read the latest SparkCMS tutorials, release notes, and best practices on accessibility, SEO, performance, and content workflows to level up your site.\",\"og_title\":\"Blogs\",\"og_description\":\"Latest blog posts and updates\",\"og_image\":\"\",\"access\":\"public\",\"canonical_url\":\"https://www.sparkcms.dev/blogs/\",\"robots\":\"index,follow\"}','blogs','Blogs','1','1751976000'),
('5','{\"id\":5,\"title\":\"Contact Us\",\"slug\":\"contact-us\",\"content\":\"<p>Reach out for demos, pricing, or support and we will respond within two business days.</p><p>Email us at hello@sparkcms.dev or use the contact form to start a conversation.</p>\",\"views\":0,\"last_modified\":1752062400,\"published\":true,\"template\":\"page.php\",\"meta_title\":\"Contact SparkCMS \\u2014 Book a Demo or Get Support\",\"meta_description\":\"Questions about SparkCMS? Book a demo, request pricing, or contact support. We\\u2019ll help you plan, migrate, and optimize your site for speed and accessibility.\",\"og_title\":\"Contact Us\",\"og_description\":\"Get in touch with us\",\"og_image\":\"\",\"access\":\"public\",\"canonical_url\":\"https://www.sparkcms.dev/contact-us/\",\"robots\":\"index,follow\"}','contact-us','Contact Us','1','1752062400'),
('7','{\"id\":7,\"title\":\"Careers\",\"slug\":\"careers\",\"content\":\"<p>Join a team that values craft, curiosity, and inclusive collaboration.</p><p>We offer flexible schedules, remote-first tooling, and opportunities to grow.</p>\",\"meta_title\":\"Careers at SparkCMS \\u2014 Build the Future of Publishing\",\"meta_description\":\"Join the SparkCMS team and help organizations launch fast, accessible websites. Explore open roles, benefits, and how we work.\",\"og_title\":\"Careers\",\"og_description\":\"Join the SparkCMS team and help build delightful digital experiences for organizations worldwide.\",\"og_image\":\"\",\"last_modified\":1758985980,\"published\":true,\"access\":\"public\",\"template\":\"page.php\",\"views\":0,\"canonical_url\":\"https://www.sparkcms.dev/careers/\",\"robots\":\"index,follow\"}','careers','Careers','1','1758985980'),
('8','{\"id\":8,\"title\":\"FAQ\",\"slug\":\"faq\",\"content\":\"<p>Find answers about hosting, security, accessibility, and onboarding.</p><p>If you need more details, contact our team for tailored guidance.</p>\",\"meta_title\":\"SparkCMS FAQ \\u2014 Answers to Common Questions\",\"meta_description\":\"Find quick answers about pricing, hosting, security, accessibility, migrations, and how SparkCMS fits your team\\u2019s workflow.\",\"og_title\":\"FAQ\",\"og_description\":\"Find answers to frequently asked questions about SparkCMS and how we support your publishing workflows.\",\"og_image\":\"\",\"last_modified\":1758985980,\"published\":true,\"access\":\"public\",\"template\":\"page.php\",\"views\":0,\"canonical_url\":\"https://www.sparkcms.dev/faq/\",\"robots\":\"index,follow\"}','faq','FAQ','1','1758985980'),
('9','{\"id\":9,\"title\":\"Portfolio\",\"slug\":\"portfolio\",\"content\":\"<p>Browse recent SparkCMS launches from nonprofits, associations, and fast-growing startups.</p><p>Each project highlights measurable gains in speed, accessibility, and editor productivity.</p>\",\"meta_title\":\"Portfolio \\u2014 SparkCMS Case Studies & Recent Work\",\"meta_description\":\"Explore sites built on SparkCMS showcasing speed, accessibility, and thoughtful design for nonprofits, associations, and growing businesses.\",\"og_title\":\"Portfolio\",\"og_description\":\"Explore recent SparkCMS projects that highlight our approach to performance, accessibility, and design.\",\"og_image\":\"\",\"last_modified\":1758985980,\"published\":true,\"access\":\"public\",\"template\":\"page.php\",\"views\":0,\"canonical_url\":\"https://www.sparkcms.dev/portfolio/\",\"robots\":\"index,follow\"}','portfolio','Portfolio','1','1758985980');

DROP TABLE IF EXISTS `cms_menus`;

CREATE TABLE `cms_menus` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`name` VARCHAR(255) NULL,
INDEX `idx_cms_menus_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_menus` (`id`,`payload`,`name`) VALUES
('1','{\"id\":1,\"name\":\"Main Menu\",\"items\":[{\"label\":\"Home\",\"link\":\"/home\"},{\"label\":\"About Us\",\"link\":\"/about-us\",\"children\":[{\"label\":\"Our Team\",\"link\":\"/about-us#team\"},{\"label\":\"Our History\",\"link\":\"/about-us#history\"},{\"label\":\"Testimonials\",\"link\":\"/testimonials\"},{\"label\":\"Careers\",\"link\":\"/careers\"}]},{\"label\":\"Services\",\"link\":\"/services\",\"children\":[{\"label\":\"Portfolio\",\"link\":\"/portfolio\"}]},{\"label\":\"Blogs\",\"link\":\"/blogs\"},{\"label\":\"Contact Us\",\"link\":\"/contact-us\",\"children\":[{\"label\":\"FAQ\",\"link\":\"/faq\"}]}]}','Main Menu'),
('2','{\"id\":2,\"name\":\"Footer Menu\",\"items\":[{\"label\":\"Home\",\"link\":\"/home\"},{\"label\":\"About Us\",\"link\":\"/about-us\"},{\"label\":\"Contact\",\"link\":\"/contact-us\"}]}','Footer Menu');

DROP TABLE IF EXISTS `cms_blog_posts`;

CREATE TABLE `cms_blog_posts` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`slug` VARCHAR(255) NULL,
`status` VARCHAR(255) NULL,
`publishDate` VARCHAR(255) NULL,
`createdAt` VARCHAR(255) NULL,
INDEX `idx_cms_blog_posts_slug` (`slug`),
INDEX `idx_cms_blog_posts_status` (`status`),
INDEX `idx_cms_blog_posts_publishDate` (`publishDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_blog_posts` (`id`,`payload`,`slug`,`status`,`publishDate`,`createdAt`) VALUES
('1','{\"id\":1,\"title\":\"Getting Started with Web Development\",\"slug\":\"getting-started-web-development\",\"excerpt\":\"A comprehensive guide for beginners looking to start their journey in web development.\",\"content\":\"<p>Welcome to the world of web development! This guide will help you understand the basics...</p>\",\"category\":\"Technology\",\"author\":\"Alice\",\"status\":\"published\",\"publishDate\":\"2024-01-15T10:00:00\",\"tags\":\"web development, beginner, tutorial\",\"createdAt\":\"2025-01-15T10:00:00\"}','getting-started-web-development','published','2024-01-15T10:00:00','2025-01-15T10:00:00'),
('2','{\"id\":2,\"title\":\"Advanced JavaScript Techniques\",\"slug\":\"advanced-javascript-techniques\",\"excerpt\":\"Explore advanced JavaScript concepts and techniques used by professional developers.\",\"content\":\"<p>JavaScript has evolved significantly over the years. In this post, we\'ll explore...</p>\",\"category\":\"Programming\",\"author\":\"Bob\",\"status\":\"draft\",\"publishDate\":\"\",\"tags\":\"javascript, advanced, programming\",\"createdAt\":\"2025-01-20T14:30:00\"}','advanced-javascript-techniques','draft','','2025-01-20T14:30:00'),
('3','{\"id\":3,\"title\":\"The Future of AI in Web Design\",\"slug\":\"future-ai-web-design\",\"excerpt\":\"How artificial intelligence is revolutionizing the way we approach web design and user experience.\",\"content\":\"<p>Artificial Intelligence is changing every industry, and web design is no exception...</p>\",\"category\":\"Design\",\"author\":\"admin\",\"status\":\"scheduled\",\"publishDate\":\"2024-02-01T09:00:00\",\"tags\":\"ai, web design, future, ux\",\"createdAt\":\"2025-01-25T16:45:00\"}','future-ai-web-design','scheduled','2024-02-01T09:00:00','2025-01-25T16:45:00'),
('4','{\"id\":4,\"title\":\"Designing Accessible Web Experiences\",\"slug\":\"designing-accessible-web-experiences\",\"excerpt\":\"Practical tips for building inclusive websites that everyone can use with ease.\",\"content\":\"<p>Accessibility should be considered at every stage of a project. Start by ensuring semantic HTML, providing sufficient color contrast, and testing with assistive technologies...</p>\",\"category\":\"Accessibility\",\"author\":\"Alice\",\"status\":\"published\",\"publishDate\":\"2024-02-05T11:15:00\",\"tags\":\"accessibility, inclusive design, web standards\",\"createdAt\":\"2025-02-02T09:30:00\"}','designing-accessible-web-experiences','published','2024-02-05T11:15:00','2025-02-02T09:30:00'),
('5','{\"id\":5,\"title\":\"Optimizing CMS Performance for Editors\",\"slug\":\"optimizing-cms-performance-editors\",\"excerpt\":\"Learn how to improve content authoring speed by tuning your CMS for performance.\",\"content\":\"<p>Editors rely on fast dashboards and responsive interfaces. Optimize database queries, leverage caching layers, and monitor slow interactions to keep your CMS running smoothly...</p>\",\"category\":\"Productivity\",\"author\":\"Bob\",\"status\":\"published\",\"publishDate\":\"2024-02-10T08:45:00\",\"tags\":\"cms, performance, productivity\",\"createdAt\":\"2025-02-07T13:20:00\"}','optimizing-cms-performance-editors','published','2024-02-10T08:45:00','2025-02-07T13:20:00'),
('6','{\"id\":6,\"title\":\"Content Strategy for Growing Startups\",\"slug\":\"content-strategy-growing-startups\",\"excerpt\":\"A framework that helps startup teams plan and execute impactful content campaigns.\",\"content\":\"<p>Startups can achieve sustainable growth with a focused content strategy. Define your audience personas, set measurable goals, and build an editorial calendar that aligns with product milestones...</p>\",\"category\":\"Marketing\",\"author\":\"aibotbob\",\"status\":\"scheduled\",\"publishDate\":\"2024-02-18T10:30:00\",\"tags\":\"content strategy, startups, marketing\",\"createdAt\":\"2025-02-09T17:10:00\"}','content-strategy-growing-startups','scheduled','2024-02-18T10:30:00','2025-02-09T17:10:00');

DROP TABLE IF EXISTS `cms_media`;

CREATE TABLE `cms_media` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`name` VARCHAR(255) NULL,
`folder` VARCHAR(255) NULL,
`type` VARCHAR(255) NULL,
`uploaded_at` VARCHAR(255) NULL,
INDEX `idx_cms_media_folder` (`folder`),
INDEX `idx_cms_media_type` (`type`),
INDEX `idx_cms_media_uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cms_users`;

CREATE TABLE `cms_users` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`username` VARCHAR(255) NULL,
`role` VARCHAR(255) NULL,
`status` VARCHAR(255) NULL,
`created_at` VARCHAR(255) NULL,
`last_login` VARCHAR(255) NULL,
`password` VARCHAR(255) NULL,
INDEX `idx_cms_users_username` (`username`),
INDEX `idx_cms_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_users` (`id`,`payload`,`username`,`role`,`status`,`created_at`,`last_login`,`password`) VALUES
('1','{\"id\":1,\"username\":\"alex.admin\",\"role\":\"admin\",\"status\":\"active\",\"created_at\":1750000000,\"last_login\":null}','alex.admin','admin','active','1750000000',NULL,'$2y$12$sBIBmKxMV1RyOEyZBlrGIu5XZYWqVv3bL7UOg3PAxcJcIRwb5KP6y'),
('2','{\"id\":2,\"username\":\"morgan.admin\",\"role\":\"admin\",\"status\":\"active\",\"created_at\":1750100000,\"last_login\":null}','morgan.admin','admin','active','1750100000',NULL,'$2y$12$sBIBmKxMV1RyOEyZBlrGIu5XZYWqVv3bL7UOg3PAxcJcIRwb5KP6y'),
('3','{\"id\":3,\"username\":\"riley.editor\",\"role\":\"editor\",\"status\":\"active\",\"created_at\":1750200000,\"last_login\":null}','riley.editor','editor','active','1750200000',NULL,'$2y$12$sBIBmKxMV1RyOEyZBlrGIu5XZYWqVv3bL7UOg3PAxcJcIRwb5KP6y'),
('4','{\"id\":4,\"username\":\"admin\",\"role\":\"admin\",\"status\":\"active\",\"created_at\":1750300000,\"last_login\":null}','admin','admin','active','1750300000',NULL,'$2y$12$sBIBmKxMV1RyOEyZBlrGIu5XZYWqVv3bL7UOg3PAxcJcIRwb5KP6y');

DROP TABLE IF EXISTS `cms_settings`;

CREATE TABLE `cms_settings` (
`setting_key` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
INDEX `idx_cms_settings_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_settings` (`setting_key`,`payload`) VALUES
('site_name','{\"setting_key\":\"site_name\",\"value\":\"SparkCMS\"}'),
('tagline','{\"setting_key\":\"tagline\",\"value\":\"Modern Content Management\"}'),
('admin_email','{\"setting_key\":\"admin_email\",\"value\":\"admin@example.com\"}'),
('homepage','{\"setting_key\":\"homepage\",\"value\":\"home\"}'),
('items_per_page','{\"setting_key\":\"items_per_page\",\"value\":10}'),
('logo','{\"setting_key\":\"logo\",\"value\":\"/images/logo.png\"}'),
('canvas_placeholder','{\"setting_key\":\"canvas_placeholder\",\"value\":\"Drag blocks from the palette to start building your page\"}'),
('social','{\"setting_key\":\"social\",\"value\":{\"facebook\":\"https://facebook.com/mywebsite\",\"twitter\":\"https://twitter.com/mywebsite\",\"instagram\":\"https://instagram.com/mywebsite\"}}');

DROP TABLE IF EXISTS `cms_forms`;

CREATE TABLE `cms_forms` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`name` VARCHAR(255) NULL,
INDEX `idx_cms_forms_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_forms` (`id`,`payload`,`name`) VALUES
('1','{\"id\":1,\"name\":\"Newsletter Signup\",\"fields\":[{\"type\":\"text\",\"label\":\"Name\",\"name\":\"name\",\"required\":false},{\"type\":\"email\",\"label\":\"Email\",\"name\":\"email\",\"required\":true},{\"type\":\"submit\",\"label\":\"Subscribe\",\"name\":\"submit\"}],\"confirmation_email\":{\"enabled\":false,\"email_field\":\"email\",\"from_name\":\"\",\"from_email\":\"\",\"subject\":\"\",\"title\":\"\",\"description\":\"\"}}','Newsletter Signup'),
('2','{\"id\":2,\"name\":\"Contact Form\",\"fields\":[{\"type\":\"text\",\"label\":\"Name\",\"name\":\"name\",\"required\":true},{\"type\":\"email\",\"label\":\"Email\",\"name\":\"email\",\"required\":true},{\"type\":\"textarea\",\"label\":\"Message\",\"name\":\"message\",\"required\":true},{\"type\":\"submit\",\"label\":\"Send Message\",\"name\":\"submit\"}],\"confirmation_email\":{\"enabled\":false,\"email_field\":\"email\",\"from_name\":\"\",\"from_email\":\"\",\"subject\":\"\",\"title\":\"\",\"description\":\"\"}}','Contact Form');

DROP TABLE IF EXISTS `cms_form_submissions`;

CREATE TABLE `cms_form_submissions` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`form_id` VARCHAR(255) NULL,
`submitted_at` VARCHAR(255) NULL,
INDEX `idx_cms_form_submissions_form_id` (`form_id`),
INDEX `idx_cms_form_submissions_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_form_submissions` (`id`,`payload`,`form_id`,`submitted_at`) VALUES
('a1b2c3d4e5f60708','{\"id\":\"a1b2c3d4e5f60708\",\"form_id\":1,\"data\":{\"name\":\"Alice Johnson\",\"email\":\"alice@example.com\"},\"meta\":{\"ip\":\"192.0.2.10\",\"user_agent\":\"Mozilla/5.0\",\"referer\":\"https://example.com/newsletter\"},\"submitted_at\":\"2024-04-01T10:15:00+00:00\",\"source\":\"https://example.com/newsletter\"}','1','2024-04-01T10:15:00+00:00'),
('b1c2d3e4f5a60708','{\"id\":\"b1c2d3e4f5a60708\",\"form_id\":1,\"data\":{\"name\":\"Brian Lee\",\"email\":\"brian.lee@example.com\"},\"meta\":{\"ip\":\"192.0.2.20\",\"user_agent\":\"Mozilla/5.0 (Macintosh)\"},\"submitted_at\":\"2024-04-05T09:45:00+00:00\",\"source\":\"\"}','1','2024-04-05T09:45:00+00:00'),
('c1d2e3f4a5b60708','{\"id\":\"c1d2e3f4a5b60708\",\"form_id\":1,\"data\":{\"name\":\"Chen Wei\",\"email\":\"chen.wei@example.org\"},\"meta\":{\"ip\":\"203.0.113.5\",\"user_agent\":\"Mozilla/5.0 (Windows NT 10.0)\"},\"submitted_at\":\"2024-04-07T18:30:00+00:00\",\"source\":\"https://example.org/subscribe\"}','1','2024-04-07T18:30:00+00:00'),
('d1e2f3a4b5c60708','{\"id\":\"d1e2f3a4b5c60708\",\"form_id\":1,\"data\":{\"name\":\"\",\"email\":\"newsletterfan@example.net\"},\"meta\":{\"ip\":\"198.51.100.42\",\"user_agent\":\"Mozilla/5.0 (Linux)\"},\"submitted_at\":\"2024-04-10T12:05:00+00:00\",\"source\":\"\"}','1','2024-04-10T12:05:00+00:00'),
('e1f2a3b4c5d60708','{\"id\":\"e1f2a3b4c5d60708\",\"form_id\":2,\"data\":{\"name\":\"Dana Scott\",\"email\":\"dana.scott@example.com\",\"message\":\"I would like more information about your services.\"},\"meta\":{\"ip\":\"192.0.2.55\",\"user_agent\":\"Mozilla/5.0\",\"referer\":\"https://example.com/contact\"},\"submitted_at\":\"2024-05-01T14:20:00+00:00\",\"source\":\"https://example.com/contact\"}','2','2024-05-01T14:20:00+00:00'),
('f1a2b3c4d5e60708','{\"id\":\"f1a2b3c4d5e60708\",\"form_id\":2,\"data\":{\"name\":\"Elena Petrova\",\"email\":\"elena.petrova@example.org\",\"message\":\"Great website!\"},\"meta\":{\"ip\":\"203.0.113.77\",\"user_agent\":\"Mozilla/5.0 (Windows NT 10.0)\"},\"submitted_at\":\"2024-05-02T08:10:00+00:00\",\"source\":\"\"}','2','2024-05-02T08:10:00+00:00'),
('g1b2c3d4e5f60708','{\"id\":\"g1b2c3d4e5f60708\",\"form_id\":2,\"data\":{\"name\":\"Hiro Tanaka\",\"email\":\"hiro.tanaka@example.jp\",\"message\":\"Can we schedule a call next week?\"},\"meta\":{\"ip\":\"198.51.100.88\",\"user_agent\":\"Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)\"},\"submitted_at\":\"2024-05-03T21:55:00+00:00\",\"source\":\"https://example.jp/contact\"}','2','2024-05-03T21:55:00+00:00'),
('h1c2d3e4f5a60708','{\"id\":\"h1c2d3e4f5a60708\",\"form_id\":2,\"data\":{\"name\":\"Jordan Smith\",\"email\":\"jordan.smith@example.net\",\"message\":\"Please update me on upcoming events.\"},\"meta\":{\"ip\":\"192.0.2.101\",\"user_agent\":\"Mozilla/5.0 (Android 14)\"},\"submitted_at\":\"2024-05-04T16:40:00+00:00\",\"source\":\"\"}','2','2024-05-04T16:40:00+00:00');

DROP TABLE IF EXISTS `cms_events`;

CREATE TABLE `cms_events` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`status` VARCHAR(255) NULL,
`start` VARCHAR(255) NULL,
`end` VARCHAR(255) NULL,
`published_at` VARCHAR(255) NULL,
INDEX `idx_cms_events_status` (`status`),
INDEX `idx_cms_events_start` (`start`),
INDEX `idx_cms_events_end` (`end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_events` (`id`,`payload`,`status`,`start`,`end`,`published_at`) VALUES
('evt_2024summit','{\"id\":\"evt_2024summit\",\"title\":\"Spark Growth Summit\",\"description\":\"<p>A two-day conference covering marketing automation, customer journeys, and retention strategies.</p>\",\"location\":\"Riverfront Convention Center\",\"start\":\"2026-11-19T09:00:00-05:00\",\"end\":\"2026-11-20T16:30:00-05:00\",\"status\":\"published\",\"tickets\":[{\"id\":\"tkt_vip_1\",\"name\":\"VIP\",\"price\":249,\"quantity\":80,\"enabled\":true},{\"id\":\"tkt_gen_1\",\"name\":\"General Admission\",\"price\":129,\"quantity\":320,\"enabled\":true}],\"categories\":[\"evtcat_conference\",\"evtcat_inperson\"],\"created_at\":\"2026-04-01T12:00:00Z\",\"published_at\":\"2026-04-01T12:05:00Z\",\"updated_at\":\"2026-04-01T12:05:00Z\"}','published','2026-11-19T09:00:00-05:00','2026-11-20T16:30:00-05:00','2026-04-01T12:05:00Z'),
('evt_brandlabs','{\"id\":\"evt_brandlabs\",\"title\":\"BrandLabs Meetup\",\"description\":\"<p>Evening networking session with product showcases and lightning talks from partner teams.</p>\",\"location\":\"Warehouse Eight\",\"start\":\"2025-12-04T18:00:00-05:00\",\"end\":\"2025-12-04T21:00:00-05:00\",\"status\":\"published\",\"tickets\":[{\"id\":\"tkt_brand_admit\",\"name\":\"General Admission\",\"price\":45,\"quantity\":150,\"enabled\":true},{\"id\":\"tkt_brand_group\",\"name\":\"Team Bundle (4)\",\"price\":160,\"quantity\":30,\"enabled\":true}],\"categories\":[\"evtcat_inperson\"],\"created_at\":\"2025-05-10T09:15:00Z\",\"published_at\":\"2025-05-12T11:00:00Z\",\"updated_at\":\"2025-05-12T11:00:00Z\"}','published','2025-12-04T18:00:00-05:00','2025-12-04T21:00:00-05:00','2025-05-12T11:00:00Z'),
('evt_bootcamp2025','{\"id\":\"evt_bootcamp2025\",\"title\":\"Digital Experience Bootcamp\",\"description\":\"<p>A two-day, hands-on training focused on designing seamless customer journeys across email, web, and mobile channels.</p>\",\"location\":\"Spark Campus East Auditorium\",\"start\":\"2025-08-14T09:30:00-04:00\",\"end\":\"2025-08-15T16:30:00-04:00\",\"status\":\"published\",\"tickets\":[{\"id\":\"tkt_bootcamp_pro\",\"name\":\"Pro Workshop Pass\",\"price\":179,\"quantity\":120,\"enabled\":true},{\"id\":\"tkt_bootcamp_virtual\",\"name\":\"Virtual Companion Pass\",\"price\":95,\"quantity\":250,\"enabled\":true}],\"categories\":[\"evtcat_inperson\",\"evtcat_training\"],\"created_at\":\"2025-04-22T14:45:00Z\",\"published_at\":\"2025-04-24T08:30:00Z\",\"updated_at\":\"2025-04-24T08:30:00Z\"}','published','2025-08-14T09:30:00-04:00','2025-08-15T16:30:00-04:00','2025-04-24T08:30:00Z'),
('evt_sparklabs26','{\"id\":\"evt_sparklabs26\",\"title\":\"SparkLabs Innovation Demo Day\",\"description\":\"<p>Showcase of emerging martech startups with live demos, investor panels, and networking lounges.</p>\",\"location\":\"Innovation District Hall\",\"start\":\"2026-06-18T12:00:00-04:00\",\"end\":\"2026-06-18T20:00:00-04:00\",\"status\":\"published\",\"tickets\":[{\"id\":\"tkt_sparklabs_vip\",\"name\":\"Investor Lounge Pass\",\"price\":295,\"quantity\":75,\"enabled\":true},{\"id\":\"tkt_sparklabs_general\",\"name\":\"Showcase Floor Pass\",\"price\":125,\"quantity\":280,\"enabled\":true}],\"categories\":[\"evtcat_inperson\",\"evtcat_conference\"],\"created_at\":\"2026-01-12T11:20:00Z\",\"published_at\":\"2026-01-13T09:00:00Z\",\"updated_at\":\"2026-01-13T09:00:00Z\"}','published','2026-06-18T12:00:00-04:00','2026-06-18T20:00:00-04:00','2026-01-13T09:00:00Z'),
('evt_automationtour25','{\"id\":\"evt_automationtour25\",\"title\":\"Automation Roadshow: Northeast\",\"description\":\"<p>A two-day traveling series featuring customer stories, product clinics, and community roundtables.</p>\",\"location\":\"Boston Exchange Center\",\"start\":\"2025-05-29T08:30:00-04:00\",\"end\":\"2025-05-30T15:30:00-04:00\",\"status\":\"published\",\"tickets\":[{\"id\":\"tkt_auto_early\",\"name\":\"Early Bird\",\"price\":79,\"quantity\":180,\"enabled\":true},{\"id\":\"tkt_auto_standard\",\"name\":\"Standard\",\"price\":109,\"quantity\":220,\"enabled\":true}],\"categories\":[\"evtcat_inperson\",\"evtcat_tour\"],\"created_at\":\"2025-02-11T13:00:00Z\",\"published_at\":\"2025-02-12T08:15:00Z\",\"updated_at\":\"2025-02-12T08:15:00Z\"}','published','2025-05-29T08:30:00-04:00','2025-05-30T15:30:00-04:00','2025-02-12T08:15:00Z'),
('evt_cxleaders2026','{\"id\":\"evt_cxleaders2026\",\"title\":\"CX Leadership Forum\",\"description\":\"<p>Peer-led sessions exploring customer experience roadmaps, AI personalization, and service design.</p>\",\"location\":\"Skyline Conference Center\",\"start\":\"2026-09-24T09:00:00-04:00\",\"end\":\"2026-09-24T17:30:00-04:00\",\"status\":\"published\",\"tickets\":[{\"id\":\"tkt_cx_vip\",\"name\":\"Executive Track\",\"price\":349,\"quantity\":90,\"enabled\":true},{\"id\":\"tkt_cx_standard\",\"name\":\"Forum Pass\",\"price\":199,\"quantity\":260,\"enabled\":true}],\"categories\":[\"evtcat_conference\"],\"created_at\":\"2026-05-08T10:10:00Z\",\"published_at\":\"2026-05-09T09:40:00Z\",\"updated_at\":\"2026-05-09T09:40:00Z\"}','published','2026-09-24T09:00:00-04:00','2026-09-24T17:30:00-04:00','2026-05-09T09:40:00Z');

DROP TABLE IF EXISTS `cms_event_orders`;

CREATE TABLE `cms_event_orders` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`event_id` VARCHAR(255) NULL,
`status` VARCHAR(255) NULL,
`ordered_at` VARCHAR(255) NULL,
INDEX `idx_cms_event_orders_event_id` (`event_id`),
INDEX `idx_cms_event_orders_status` (`status`),
INDEX `idx_cms_event_orders_ordered_at` (`ordered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_event_orders` (`id`,`payload`,`event_id`,`status`,`ordered_at`) VALUES
('ORD-10245','{\"id\":\"ORD-10245\",\"event_id\":\"evt_2024summit\",\"buyer_name\":\"Maya Singh\",\"tickets\":[{\"ticket_id\":\"tkt_vip_1\",\"quantity\":2,\"price\":249},{\"ticket_id\":\"tkt_gen_1\",\"quantity\":1,\"price\":129}],\"amount\":627,\"status\":\"paid\",\"ordered_at\":\"2024-07-02T14:32:00Z\"}','evt_2024summit','paid','2024-07-02T14:32:00Z'),
('ORD-10278','{\"id\":\"ORD-10278\",\"event_id\":\"evt_2024summit\",\"buyer_name\":\"Lucas Hern\\u00e1ndez\",\"tickets\":[{\"ticket_id\":\"tkt_gen_1\",\"quantity\":4,\"price\":129}],\"amount\":516,\"status\":\"paid\",\"ordered_at\":\"2024-07-08T10:05:00Z\"}','evt_2024summit','paid','2024-07-08T10:05:00Z'),
('ORD-20411','{\"id\":\"ORD-20411\",\"event_id\":\"evt_brandlabs\",\"buyer_name\":\"Sasha Lee\",\"tickets\":[{\"ticket_id\":\"tkt_brand_admit\",\"quantity\":3,\"price\":45}],\"amount\":135,\"status\":\"paid\",\"ordered_at\":\"2024-06-21T09:58:00Z\"}','evt_brandlabs','paid','2024-06-21T09:58:00Z'),
('ORD-20452','{\"id\":\"ORD-20452\",\"event_id\":\"evt_brandlabs\",\"buyer_name\":\"Jordan Carter\",\"tickets\":[{\"ticket_id\":\"tkt_brand_admit\",\"quantity\":2,\"price\":45}],\"amount\":90,\"status\":\"refunded\",\"ordered_at\":\"2024-06-25T15:20:00Z\"}','evt_brandlabs','refunded','2024-06-25T15:20:00Z');

DROP TABLE IF EXISTS `cms_event_categories`;

CREATE TABLE `cms_event_categories` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`slug` VARCHAR(255) NULL,
INDEX `idx_cms_event_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_event_categories` (`id`,`payload`,`slug`) VALUES
('evtcat_conference','{\"id\":\"evtcat_conference\",\"name\":\"Conference\",\"slug\":\"conference\",\"created_at\":\"2024-06-01T12:00:00Z\",\"updated_at\":\"2024-06-01T12:00:00Z\"}','conference'),
('evtcat_inperson','{\"id\":\"evtcat_inperson\",\"name\":\"In-person\",\"slug\":\"in-person\",\"created_at\":\"2024-06-01T12:00:00Z\",\"updated_at\":\"2024-06-01T12:00:00Z\"}','in-person'),
('evtcat_virtual','{\"id\":\"evtcat_virtual\",\"name\":\"Virtual\",\"slug\":\"virtual\",\"created_at\":\"2024-06-01T12:00:00Z\",\"updated_at\":\"2024-06-01T12:00:00Z\"}','virtual');

DROP TABLE IF EXISTS `cms_event_forms`;

CREATE TABLE `cms_event_forms` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`name` VARCHAR(255) NULL,
INDEX `idx_cms_event_forms_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_event_forms` (`id`,`payload`,`name`) VALUES
('evt_form_registration','{\"id\":\"evt_form_registration\",\"name\":\"Event registration\"}','Event registration'),
('evt_form_vip_rsvp','{\"id\":\"evt_form_vip_rsvp\",\"name\":\"VIP RSVP\"}','VIP RSVP'),
('evt_form_webinar','{\"id\":\"evt_form_webinar\",\"name\":\"Webinar signup\"}','Webinar signup'),
('evt_form_waitlist','{\"id\":\"evt_form_waitlist\",\"name\":\"Waitlist request\"}','Waitlist request');

DROP TABLE IF EXISTS `cms_map_locations`;

CREATE TABLE `cms_map_locations` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`category` VARCHAR(255) NULL,
`title` VARCHAR(255) NULL,
INDEX `idx_cms_map_locations_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_map_locations` (`id`,`payload`,`category`,`title`) VALUES
('map_loc_central_hq','{\"id\":\"map_loc_central_hq\",\"name\":\"Central Headquarters\",\"slug\":\"central-headquarters\",\"status\":\"published\",\"description\":\"Our primary campus with executive offices, conference facilities, and an on-site cafe for guests.\",\"address\":{\"street\":\"500 Market Street\",\"city\":\"Seattle\",\"region\":\"WA\",\"postal_code\":\"98104\",\"country\":\"USA\"},\"coordinates\":{\"lat\":47.603832,\"lng\":-122.330062},\"contact\":{\"phone\":\"+1 (206) 555-0199\",\"email\":\"hq@spark.example\",\"website\":\"https://spark.example/locations/seattle\"},\"category_ids\":[\"map_cat_general\",\"map_cat_office\"],\"image_ids\":[],\"tags\":[\"executive\",\"meeting rooms\"],\"hours\":\"Mon-Fri 8:00 AM \\u2013 6:00 PM\",\"accessibility_notes\":\"Accessible entrances on 5th Ave with elevators to all floors.\",\"created_at\":\"2023-06-01T09:00:00+00:00\",\"updated_at\":\"2023-12-15T14:30:00+00:00\"}',NULL,NULL),
('map_loc_innovation_lab','{\"id\":\"map_loc_innovation_lab\",\"name\":\"Innovation Lab\",\"slug\":\"innovation-lab\",\"status\":\"published\",\"description\":\"Prototype new ideas alongside the engineering and design teams in an open coworking environment.\",\"address\":{\"street\":\"2200 Mission Street\",\"city\":\"San Francisco\",\"region\":\"CA\",\"postal_code\":\"94110\",\"country\":\"USA\"},\"coordinates\":{\"lat\":37.762222,\"lng\":-122.419379},\"contact\":{\"phone\":\"+1 (415) 555-0111\",\"email\":\"lab@spark.example\",\"website\":\"https://spark.example/locations/innovation-lab\"},\"category_ids\":[\"map_cat_cowork\"],\"image_ids\":[],\"tags\":[\"coworking\",\"prototyping\"],\"hours\":\"Mon-Fri 7:00 AM \\u2013 8:00 PM\",\"accessibility_notes\":\"Ramp access available from Mission Street entrance; assistive listening devices at reception.\",\"created_at\":\"2023-07-18T11:15:00+00:00\",\"updated_at\":\"2024-01-20T10:05:00+00:00\"}',NULL,NULL),
('map_loc_brooklyn_studio','{\"id\":\"map_loc_brooklyn_studio\",\"name\":\"Brooklyn Creative Studio\",\"slug\":\"brooklyn-creative-studio\",\"status\":\"published\",\"description\":\"Flexible content production space with podcast booths and video recording suites.\",\"address\":{\"street\":\"85 Wythe Avenue\",\"city\":\"Brooklyn\",\"region\":\"NY\",\"postal_code\":\"11249\",\"country\":\"USA\"},\"coordinates\":{\"lat\":40.721786,\"lng\":-73.958111},\"contact\":{\"phone\":\"+1 (718) 555-0176\",\"email\":\"brooklyn@spark.example\",\"website\":\"https://spark.example/locations/brooklyn-studio\"},\"category_ids\":[\"map_cat_cowork\",\"map_cat_general\"],\"image_ids\":[],\"tags\":[\"studio\",\"media\"],\"hours\":\"Mon-Sun 9:00 AM \\u2013 9:00 PM\",\"accessibility_notes\":\"All studios include height-adjustable desks and tactile signage.\",\"created_at\":\"2023-10-10T13:40:00+00:00\",\"updated_at\":\"2024-03-12T09:20:00+00:00\"}',NULL,NULL),
('map_loc_chicago_support','{\"id\":\"map_loc_chicago_support\",\"name\":\"Chicago Support Center\",\"slug\":\"chicago-support-center\",\"status\":\"published\",\"description\":\"Customer support and onboarding specialists serving the Midwest region.\",\"address\":{\"street\":\"233 Wacker Drive\",\"city\":\"Chicago\",\"region\":\"IL\",\"postal_code\":\"60606\",\"country\":\"USA\"},\"coordinates\":{\"lat\":41.886337,\"lng\":-87.635498},\"contact\":{\"phone\":\"+1 (312) 555-0128\",\"email\":\"support-midwest@spark.example\",\"website\":\"https://spark.example/locations/chicago-support\"},\"category_ids\":[\"map_cat_general\",\"map_cat_office\"],\"image_ids\":[],\"tags\":[\"customer success\",\"training\"],\"hours\":\"Mon-Fri 8:30 AM \\u2013 5:30 PM\",\"accessibility_notes\":\"Hearing loop installed in briefing rooms and service animals welcome.\",\"created_at\":\"2023-11-01T15:00:00+00:00\",\"updated_at\":\"2024-02-28T10:10:00+00:00\"}',NULL,NULL),
('map_loc_boston_learning','{\"id\":\"map_loc_boston_learning\",\"name\":\"Boston Learning Annex\",\"slug\":\"boston-learning-annex\",\"status\":\"published\",\"description\":\"Classroom-style workspace offering certification workshops and professional development.\",\"address\":{\"street\":\"120 Tremont Street\",\"city\":\"Boston\",\"region\":\"MA\",\"postal_code\":\"02108\",\"country\":\"USA\"},\"coordinates\":{\"lat\":42.357152,\"lng\":-71.061523},\"contact\":{\"phone\":\"+1 (617) 555-0183\",\"email\":\"learning@spark.example\",\"website\":\"https://spark.example/locations/boston-learning\"},\"category_ids\":[\"map_cat_general\"],\"image_ids\":[],\"tags\":[\"training\",\"events\"],\"hours\":\"Mon-Sat 8:00 AM \\u2013 9:00 PM\",\"accessibility_notes\":\"All classrooms include caption-enabled displays and wheelchair-accessible seating rows.\",\"created_at\":\"2024-01-08T09:30:00+00:00\",\"updated_at\":\"2024-03-22T11:45:00+00:00\"}',NULL,NULL),
('map_loc_denver_operations','{\"id\":\"map_loc_denver_operations\",\"name\":\"Denver Operations Hub\",\"slug\":\"denver-operations-hub\",\"status\":\"published\",\"description\":\"Mountain region base for fleet coordination and on-site deployment teams.\",\"address\":{\"street\":\"1776 Champa Street\",\"city\":\"Denver\",\"region\":\"CO\",\"postal_code\":\"80202\",\"country\":\"USA\"},\"coordinates\":{\"lat\":39.746841,\"lng\":-104.991531},\"contact\":{\"phone\":\"+1 (720) 555-0104\",\"email\":\"denver@spark.example\",\"website\":\"https://spark.example/locations/denver-operations\"},\"category_ids\":[\"map_cat_distribution\",\"map_cat_office\"],\"image_ids\":[],\"tags\":[\"logistics\",\"field ops\"],\"hours\":\"Mon-Sun 6:00 AM \\u2013 10:00 PM\",\"accessibility_notes\":\"Dockside elevators provide barrier-free access to command rooms.\",\"created_at\":\"2024-01-22T07:45:00+00:00\",\"updated_at\":\"2024-03-30T14:15:00+00:00\"}',NULL,NULL),
('map_loc_calgary_service','{\"id\":\"map_loc_calgary_service\",\"name\":\"Calgary Service Depot\",\"slug\":\"calgary-service-depot\",\"status\":\"published\",\"description\":\"Prairie provinces service depot handling device maintenance and deployment logistics.\",\"address\":{\"street\":\"401 9 Ave SW\",\"city\":\"Calgary\",\"region\":\"AB\",\"postal_code\":\"T2P 3C5\",\"country\":\"Canada\"},\"coordinates\":{\"lat\":51.046095,\"lng\":-114.068535},\"contact\":{\"phone\":\"+1 (587) 555-0187\",\"email\":\"calgary@spark.example\",\"website\":\"https://spark.example/locations/calgary-service\"},\"category_ids\":[\"map_cat_distribution\"],\"image_ids\":[],\"tags\":[\"maintenance\",\"fleet\"],\"hours\":\"Mon-Sat 7:00 AM \\u2013 5:00 PM\",\"accessibility_notes\":\"Tool cribs and loading areas equipped with anti-slip flooring and high-contrast signage.\",\"created_at\":\"2023-10-22T06:40:00+00:00\",\"updated_at\":\"2024-03-08T15:05:00+00:00\"}',NULL,NULL),
('map_loc_ottawa_policy','{\"id\":\"map_loc_ottawa_policy\",\"name\":\"Ottawa Policy Office\",\"slug\":\"ottawa-policy-office\",\"status\":\"published\",\"description\":\"Government relations team focusing on technology advocacy and regulatory partnerships.\",\"address\":{\"street\":\"350 Albert Street\",\"city\":\"Ottawa\",\"region\":\"ON\",\"postal_code\":\"K1R 1A4\",\"country\":\"Canada\"},\"coordinates\":{\"lat\":45.420356,\"lng\":-75.701778},\"contact\":{\"phone\":\"+1 (613) 555-0150\",\"email\":\"ottawa@spark.example\",\"website\":\"https://spark.example/locations/ottawa-policy\"},\"category_ids\":[\"map_cat_office\"],\"image_ids\":[],\"tags\":[\"policy\",\"government\"],\"hours\":\"Mon-Fri 8:30 AM \\u2013 5:00 PM\",\"accessibility_notes\":\"Meeting suites equipped with automatic door operators and speech-to-text display panels.\",\"created_at\":\"2023-11-18T10:20:00+00:00\",\"updated_at\":\"2024-03-14T09:55:00+00:00\"}',NULL,NULL);

DROP TABLE IF EXISTS `cms_map_categories`;

CREATE TABLE `cms_map_categories` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`slug` VARCHAR(255) NULL,
`name` VARCHAR(255) NULL,
INDEX `idx_cms_map_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `cms_map_categories` (`id`,`payload`,`slug`,`name`) VALUES
('map_cat_general','{\"id\":\"map_cat_general\",\"name\":\"General\",\"slug\":\"general\",\"color\":\"#2D70F5\",\"icon\":\"fa-location-dot\",\"sort_order\":1,\"is_default\":true}','general','General'),
('map_cat_office','{\"id\":\"map_cat_office\",\"name\":\"Offices\",\"slug\":\"offices\",\"color\":\"#00A389\",\"icon\":\"fa-building\",\"sort_order\":2,\"is_default\":true}','offices','Offices'),
('map_cat_cowork','{\"id\":\"map_cat_cowork\",\"name\":\"Coworking Spaces\",\"slug\":\"coworking-spaces\",\"color\":\"#7C3AED\",\"icon\":\"fa-people-group\",\"sort_order\":3,\"is_default\":false}','coworking-spaces','Coworking Spaces'),
('map_cat_distribution','{\"id\":\"map_cat_distribution\",\"name\":\"Distribution Centers\",\"slug\":\"distribution-centers\",\"color\":\"#F97316\",\"icon\":\"fa-truck-ramp-box\",\"sort_order\":4,\"is_default\":false}','distribution-centers','Distribution Centers');

DROP TABLE IF EXISTS `cms_page_history`;

CREATE TABLE `cms_page_history` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`page_id` VARCHAR(255) NULL,
`saved_at` VARCHAR(255) NULL,
INDEX `idx_cms_page_history_page_id` (`page_id`),
INDEX `idx_cms_page_history_saved_at` (`saved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `cms_speed_snapshots`;

CREATE TABLE `cms_speed_snapshots` (
`id` VARCHAR(191) PRIMARY KEY,
`payload` LONGTEXT NOT NULL,
`captured_at` VARCHAR(255) NULL,
INDEX `idx_cms_speed_snapshots_captured_at` (`captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
