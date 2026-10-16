<?php
/**
 * GNCILink AI Assistant - drop-in widget.
 *
 * Add ONE line to components/footer.php (just before </body> or at its end):
 *     <?php include_once __DIR__ . '/../chatbot/widget.php'; ?>
 *
 * Optional, on pages that belong to a program/department, set before the footer include:
 *     <?php $chatbotProgram = 'BSIT'; $chatbotDept = 'cba'; ?>
 * (If $chatbotProgram is not set, ?program=CODE in the URL is used, like the homepage events card.)
 *
 * Renders nothing twice, makes no DB calls, and exposes no secrets.
 */
if (defined('GNCBOT_WIDGET_RENDERED')) { return; }
define('GNCBOT_WIDGET_RENDERED', true);

// URL of this folder, derived from where it really sits under the web root (works in a sub-folder too).
// Override with $chatbotBase = '/some/path/chatbot'; before the include if your host needs it.
$gncbotBase = '/chatbot';
$gncbotDoc  = rtrim(str_replace('\\', '/', (string)realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''))), '/');
$gncbotDir  = str_replace('\\', '/', (string)realpath(__DIR__));
if ($gncbotDoc !== '' && strpos($gncbotDir, $gncbotDoc . '/') === 0) { $gncbotBase = substr($gncbotDir, strlen($gncbotDoc)); }
if (isset($chatbotBase) && is_string($chatbotBase) && preg_match('#^/[A-Za-z0-9_\-./]*$#', $chatbotBase)) { $gncbotBase = rtrim($chatbotBase, '/'); }
$gncbotProg = $chatbotProgram ?? ($_GET['program'] ?? '');
$gncbotProg = (is_string($gncbotProg) && preg_match('/^[A-Za-z\-]{2,10}$/', $gncbotProg) && strcasecmp($gncbotProg, 'ALL') !== 0) ? $gncbotProg : '';
$gncbotDept = $chatbotDept ?? '';
$gncbotDept = (is_string($gncbotDept) && preg_match('/^[A-Za-z0-9\-]{1,50}$/', $gncbotDept)) ? $gncbotDept : '';
$gncbotVer  = static function (string $f): string { $p = __DIR__ . '/' . $f; return is_file($p) ? (string)filemtime($p) : '1'; };
?>
<style>#gncbot:not(.gncbot-ready){display:none}</style><?php /* stays hidden unless chatbot.css really loaded (see chatbot.js) */ ?>
<link rel="stylesheet" href="<?= $gncbotBase ?>/chatbot.css?v=<?= $gncbotVer('chatbot.css') ?>">
<div id="gncbot" class="gncbot" data-endpoint="<?= $gncbotBase ?>/chatbot.php"
     data-program="<?= htmlspecialchars($gncbotProg, ENT_QUOTES) ?>" data-dept="<?= htmlspecialchars($gncbotDept, ENT_QUOTES) ?>">
    <button type="button" class="gncbot-launcher" aria-label="Open GNCILink AI Assistant" aria-expanded="false" aria-controls="gncbot-panel">
        <svg class="gncbot-ico-chat" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-4.3 3.4A.6.6 0 0 1 3.7 21V18H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm4 5.2a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2Zm4 0a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2Zm4 0a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2Z"/></svg>
        <svg class="gncbot-ico-close" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
        <span class="gncbot-launcher-label">Ask GNCILink AI</span>
    </button>

    <section id="gncbot-panel" class="gncbot-panel" role="dialog" aria-label="GNCILink AI Assistant" hidden>
        <header class="gncbot-header">
            <span class="gncbot-avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M12 2l1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8L12 2Zm7 11l.9 2.6L22.5 16l-2.6.9L19 19.5l-.9-2.6-2.6-.9 2.6-.9L19 13ZM6 15l.7 2.3L9 18l-2.3.7L6 21l-.7-2.3L3 18l2.3-.7L6 15Z"/></svg>
            </span>
            <div class="gncbot-titles">
                <h2 class="gncbot-title">GNCILink AI Assistant</h2>
                <p class="gncbot-sub">Guagua National Colleges</p>
            </div>
            <button type="button" class="gncbot-iconbtn gncbot-new" aria-label="Start a new conversation" title="New conversation">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg>
            </button>
            <button type="button" class="gncbot-iconbtn gncbot-close" aria-label="Close chat">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="gncbot-messages" role="log" aria-live="polite" aria-relevant="additions"></div>
        <div class="gncbot-suggestions" aria-label="Suggested questions"></div>

        <form class="gncbot-form" autocomplete="off">
            <label for="gncbot-input" class="gncbot-sr">Type your question</label>
            <textarea id="gncbot-input" class="gncbot-input" rows="1" maxlength="500" placeholder="Ask about GNC programs, events, offices..." enterkeyhint="send"></textarea>
            <button type="submit" class="gncbot-send" aria-label="Send message">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4 21 12 3.4 3.6 3.3 10l12.6 2-12.6 2 .1 6.4Z"/></svg>
            </button>
        </form>
        <p class="gncbot-note">AI can make mistakes. Please confirm important details with GNC.</p>
    </section>
</div>
<script src="<?= $gncbotBase ?>/chatbot.js?v=<?= $gncbotVer('chatbot.js') ?>" defer></script>
