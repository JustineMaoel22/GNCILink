<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/contact-number.php';
$checks=0;
function contactCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
foreach ([
    '09123456789'=>'+63-912-345-6789',
    '+639123456789'=>'+63-912-345-6789',
    '9123456789'=>'+63-912-345-6789',
    '+63-912-345-6789'=>'+63-912-345-6789',
    '(045) 900-4473 Loc. 106'=>'045-900-4473 loc. 106',
    '0459004473 ext 00123'=>'045-900-4473 loc. 00123',
    '900-4473'=>'045-900-4473',
    '045-000-0000'=>'045-000-0000',
    ''=>'',
] as $input=>$expected) {
    $input=(string)$input;
    contactCheck(contactNumberSave($input)===$expected,'Canonical formatting: '.$input);
    contactCheck(contactNumberSave($expected)===$expected,'Round trip: '.$expected);
}
foreach (['09123','+6391234567890','045-123-456','045-123-45678','045-123-4567 loc. abc','045-123-4567 loc. 1234567',
    '+63-912-345-6789 loc. 123','abc09123456789','+1-912-345-6789','045-123-4567 / 045-765-4321','<img src=x>0451234567'] as $value) {
    try {contactNumberSave($value);throw new LogicException('Invalid number accepted: '.$value);}
    catch (InvalidArgumentException $expected) {$checks++;}
}
contactCheck(contactNumberSave('legacy text', 'legacy text')==='legacy text','Unchanged legacy text survives unrelated saves.');
try {contactNumberSave('new bad text', 'legacy text');throw new LogicException('Legacy edit bypassed validation.');}
catch (InvalidArgumentException $expected) {$checks++;}
contactCheck(contactNumberDisplay('(045) 900-4473 Loc. 106')==='045-900-4473 loc. 106','Public legacy formatting.');
contactCheck(contactNumberDisplay('old unknown format')==='old unknown format','Unknown saved content preserved.');
contactCheck(contactNumberTel('045-900-4473 loc. 106')==='tel:0459004473;ext=106','Extension is not appended to main dial digits.');
contactCheck(contactNumberTel('09123456789')==='tel:+639123456789','Mobile dial URI.');
contactCheck(contactNumberTel('old unknown format')==='','Unrecognized text does not create dial URI.');
contactCheck(contactExtensionSave('00123')==='00123','Extension leading zeroes preserved.');
foreach (['1234567','12ab','1-2'] as $extension) {
    try {contactExtensionSave($extension);throw new LogicException('Invalid extension accepted.');}
    catch (InvalidArgumentException $expected) {$checks++;}
}
echo "Contact number policy: $checks checks passed.\n";
