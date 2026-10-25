<?php
require_once __DIR__.'/../includes/content-publish-policy.php';
/** Plain text and directory values edited through the existing section workflow. */
function contactPageDefaults(): array {
    $contact = [
    'address'  => 'Rep. Eligio Lagman St, Sta. Filomena Rd, Guagua, Pampanga',
    'phone'    => '(045) 123-4567',
    'email'    => 'justinemaoel.gnc@gmail.com',
    'hours'    => 'Monday - Friday, 8:00 AM - 5:00 PM',
    'facebook' => 'https://www.facebook.com/gnc.edu.ph',
];
    $directDirectory = [
    ['900-4473', "President's Office"],
    ['900-0341', 'College Registrar Office'],
    ['900-2207', 'Accounting Office'],
];
    $localDirectory = [
    ['101', "President's Office/Operator/Boardroom"],
    ['105', 'Human Resources Office'],
    ['106', 'College Registrar'],
    ['107', 'Accounting Office'],
    ['108', 'College Guidance'],
    ['109', 'Medical Clinic/Dental Clinic'],
    ['109', 'Office of the Student Affairs'],
    ['113', 'College Library'],
    ['115', 'Elementary Principal Office/Printing'],
    ['116', 'High School Library'],
    ['117', 'High School Principal Office'],
    ['119', 'School Electrician'],
    ['120', 'CASEd Office (Education/Arts and Sciences)'],
    ['121', 'Montessori Office'],
    ['122', 'Guardhouse (Main Gate)'],
    ['123', 'Purchasing Office'],
    ['124', 'Computer Technician Office'],
    ['125', 'College of Engineering Office'],
    ['137', 'CAMP Office'],
    ['138', 'Head, Instruction Cluster (HIC) Office'],
];
    $fields = [
        'hero_kicker' => ['content' => 'Get in touch', 'title' => 'Hero label', 'max' => 150],
        'hero_title' => ['content' => 'Contact', 'title' => 'Heading', 'max' => 150],
        'hero_accent' => ['content' => 'Us', 'title' => 'Heading accent', 'max' => 150],
        'hero_description' => ['content' => 'Questions about admissions, programs, or campus life? Send us a message and the GNC team will get back to you.', 'title' => 'Introduction', 'max' => 2000],
        'info_title' => ['content' => 'Reach Out to GNC', 'title' => 'Contact card heading', 'max' => 150],
        'info_description' => ['content' => 'We\'re happy to help. Use any of the channels below or send us a message.', 'title' => 'Contact card introduction', 'max' => 2000],
        'address_label' => ['content' => 'Address', 'title' => 'Address label', 'max' => 150],
        'phone_label' => ['content' => 'Phone', 'title' => 'Phone label', 'max' => 150],
        'email_label' => ['content' => 'Email', 'title' => 'Email label', 'max' => 150],
        'hours_label' => ['content' => 'Office Hours', 'title' => 'Hours label', 'max' => 150],
        'map_label' => ['content' => 'Campus Map', 'title' => 'Campus map button', 'max' => 150],
        'facebook_label' => ['content' => 'Facebook', 'title' => 'Facebook button', 'max' => 150],
        'form_title' => ['content' => 'Send a Message', 'title' => 'Form heading', 'max' => 150],
        'form_description' => ['content' => 'Fields marked with * are required.', 'title' => 'Form instructions', 'max' => 2000],
        'name_label' => ['content' => 'Full Name', 'title' => 'Name field label', 'max' => 150],
        'form_email_label' => ['content' => 'Email Address', 'title' => 'Email field label', 'max' => 150],
        'subject_label' => ['content' => 'Subject', 'title' => 'Subject field label', 'max' => 150],
        'message_label' => ['content' => 'Message', 'title' => 'Message field label', 'max' => 150],
        'submit_label' => ['content' => 'Send Message', 'title' => 'Send button label', 'max' => 150],
        'directory_title' => ['content' => 'School Directory', 'title' => 'Directory heading', 'max' => 150],
        'directory_description' => ['content' => 'Find the right office and its phone line.', 'title' => 'Directory introduction', 'max' => 2000],
        'direct_title' => ['content' => 'Direct Lines', 'title' => 'Direct lines heading', 'max' => 150],
        'local_title' => ['content' => 'Local Lines', 'title' => 'Local lines heading', 'max' => 150],
    ];
    foreach ($contact as $key => $value) $fields['contact_' . $key] = ['content' => $value, 'title' => 'Contact ' . $key, 'max' => $key === 'address' ? 500 : 255];
    foreach (['direct' => $directDirectory, 'local' => $localDirectory] as $type => $rows) {
        foreach ($rows as $i => [$number, $office]) {
            $fields[$type . '_' . $i . '_number'] = ['content' => $number, 'title' => $office . ' phone number', 'max' => 50];
            $fields[$type . '_' . $i . '_office'] = ['content' => $office, 'title' => $type . ' directory office ' . ($i + 1), 'max' => 150];
        }
    }
    return $fields;
}
function contactPagePlain(string $value): string {
    return trim(richTextLooksLikeHtml($value) ? html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value);
}
function contactPageValidate(string $key, string $value): string {
    $fields = contactPageDefaults();
    if (!isset($fields[$key])) throw new InvalidArgumentException('Unknown Contact Us field.');
    $value = contactPagePlain($value);
    if (($value === '' && !contentPublishAllowsIncomplete()) || mb_strlen($value) > $fields[$key]['max']) throw new InvalidArgumentException('Enter ' . strtolower($fields[$key]['title']) . ' of up to ' . $fields[$key]['max'] . ' characters.');
    if ($value === '' && contentPublishAllowsIncomplete()) return '';
    if ($key === 'contact_email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $value))) throw new InvalidArgumentException('Enter a valid contact email address.');
    if ($key === 'contact_facebook' && !preg_match('~^https?://(?:www\.)?facebook\.com/[^\s]*$~iD', $value)) throw new InvalidArgumentException('Use a Facebook page URL starting with https://www.facebook.com/.');
    if (($key === 'contact_phone' || str_ends_with($key, '_number')) && !preg_match('/\d/', $value)) throw new InvalidArgumentException('Enter a phone number containing digits.');
    return $value;
}
function contactPageContent(bool $preview): array {
    $fields = contactPageDefaults();
    foreach (getPageSections('contact-us') as $section) {
        $key = $section['section_key'];
        if (!isset($fields[$key])) continue;
        $value = $preview && $section['draft_content'] !== null ? $section['draft_content'] : $section['content'];
        $fields[$key]['content'] = contactPagePlain((string)$value);
    }
    return $fields;
}
