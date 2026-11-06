<?php
/** Contact numbers use an unambiguous canonical string in the existing CMS columns. */
function contactNumberParse(string $value): ?array {
    $value = trim($value);
    if ($value === '') return ['type'=>'mobile','number'=>'','extension'=>'','display'=>''];
    $extension = '';
    if (preg_match('/\s*(?:loc(?:al)?\.?|ext(?:ension)?\.?|#)\s*([0-9]{1,6})\s*$/i', $value, $match)) {
        $extension = $match[1];
        $value = trim(substr($value, 0, -strlen($match[0])));
    }
    if (!preg_match('/\A\+?[0-9()\s-]+\z/D', $value)) return null;
    $digits = preg_replace('/[^0-9]/', '', $value);
    if (str_starts_with($value, '+') && !str_starts_with($digits, '63')) return null;
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) $digits = substr($digits, 2);
    elseif (strlen($digits) === 11 && str_starts_with($digits, '09')) $digits = substr($digits, 1);
    elseif (strlen($digits) === 7 && !str_starts_with($value, '+')) $digits = '045' . $digits;
    if (strlen($digits) !== 10) return null;
    $type = str_starts_with($digits, '045') && !str_starts_with($value, '+') ? 'landline' : 'mobile';
    if ($type === 'mobile' && ($extension !== '' || str_starts_with($digits, '0'))) return null;
    $display = $type === 'mobile'
        ? '+63-' . substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6)
        : '045-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    if ($extension !== '') $display .= ' loc. ' . $extension;
    return ['type'=>$type,'number'=>$digits,'extension'=>$extension,'display'=>$display];
}

function contactNumberSave(string $value, ?string $previous = null): string {
    $value = trim($value);
    $parsed = contactNumberParse($value);
    if ($parsed !== null) return $parsed['display'];
    // Existing unrecognized/multiple-number text survives unrelated edits only.
    if ($previous !== null && $value === trim($previous)) return $value;
    throw new InvalidArgumentException('Enter a complete mobile number (+63-000-000-0000) or landline (045-000-0000), with an optional numeric extension of up to 6 digits.');
}

function contactExtensionSave(string $value, ?string $previous = null): string {
    $value = trim($value);
    if ($value === '' || preg_match('/\A[0-9]{1,6}\z/D', $value)) return $value;
    if ($previous !== null && $value === trim($previous)) return $value;
    throw new InvalidArgumentException('Enter a local number containing 1–6 digits.');
}

function contactNumberDisplay(?string $value): string {
    $value = (string)$value;
    return contactNumberParse($value)['display'] ?? $value;
}

function contactNumberTel(?string $value): string {
    $parsed = contactNumberParse((string)$value);
    if (!$parsed || $parsed['number'] === '') return '';
    $tel = ($parsed['type'] === 'mobile' ? '+63' : '') . $parsed['number'];
    return 'tel:' . $tel . ($parsed['extension'] !== '' ? ';ext=' . $parsed['extension'] : '');
}

/** Content Editors enter the approval queue before normal save handlers execute. */
function contactNumberProposal(string $route, array $post): array {
    if (!in_array($route, ['/admin/action/page-content-handler.php','/admin/action/page-review-handler.php'], true)) return $post;
    $action=$post['action'] ?? '';
    if ($route==='/admin/action/page-content-handler.php' && in_array($action,['admin_save_office','save_department_dean'],true)) {
        $office=$action==='admin_save_office';
        $field=$office?'phone':'dean_phone';
        if (!is_string($post[$field] ?? '')) throw new InvalidArgumentException('Enter a valid contact number.');
        $q=getDB()->prepare($office ? 'SELECT phone FROM admin_offices WHERE office_id=?' : 'SELECT dean_phone FROM departments WHERE dept_id=?');
        $q->execute([(int)($post[$office?'office_id':'dept_id'] ?? 0)]);
        $previous=$q->fetchColumn();
        $post[$field]=contactNumberSave($post[$field]??'',is_string($previous)?$previous:null);
    }
    if (($post['page']??'')==='contact-us' && array_key_exists('content',$post)
        && (($post['section_key']??'')==='contact_phone' || preg_match('/^(direct|local)_[0-9]+_number$/D',(string)($post['section_key']??'')))) {
        if (!is_string($post['content'])) throw new InvalidArgumentException('Enter a valid contact number.');
        require_once __DIR__.'/../components/contact-page-content.php';
        $post['content']=contactPageValidate($post['section_key'],$post['content'],getSectionContent('contact-us',$post['section_key']));
    }
    return $post;
}
