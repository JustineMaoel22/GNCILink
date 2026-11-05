<?php
require_once __DIR__.'/admin-onboarding.php';

/** Code-managed help, filtered through the same permissions as the walkthrough. */
function adminHelpTopics(): array {
    $super=rbacIsSuper();$mirror=rbacRole()==='The Mirror';
    $topics=[[
        'id'=>'getting-started','title'=>'Getting Started','icon'=>'compass','route'=>null,'cta'=>'',
        'summary'=>'Set up your account and find your way around the admin panel.',
        'steps'=>['Sign in with the email and temporary password shared privately by your Super Admin.','Enter the verification code sent to your email. Check your spam folder if it has not arrived.','When prompted, replace the temporary password with your own password before continuing.','Use the sidebar to open a feature. On a phone or tablet, use the menu button at the top.'],
        'note'=>'Your menu and available actions depend on your assigned role. Never share passwords or verification codes.',
        'keywords'=>'login log in sign in onboarding first time temporary password email verification otp code navigation menu permissions role access',
        'faqs'=>[
            ['question'=>'What is the difference between the three roles?','answer'=>'Super Admins manage users and approve changes. Content Editors prepare website content and submit changes for approval. The Mirror creates or edits its own news and announcements and submits them for approval. Your menu shows the features available to you.'],
            ['question'=>'Why is a feature missing from my menu?','answer'=>'Some features are restricted by role. Ask your Super Admin to check your assigned access. The walkthrough follows the same permissions as your menu.'],
            ['question'=>'What if my verification email does not arrive?','answer'=>'Check your spam folder and the email address you used. Use the resend option on the verification screen if available. If the screen asks you to wait, try again after that wait. Contact your Super Admin if the issue continues.'],
        ],
    ]];
    $details=[
        '/admin/admin-dash.php'=>[
            'id'=>'dashboard','icon'=>'grid-1x2','summary'=>'Find recent updates and shortcuts to your work.','cta'=>'Go to your dashboard',
            'steps'=>['Check the content counts and recent updates available to your role.','Use a shortcut or the sidebar to open the item you want to work on.','Check the success or error message after saving, then return to the dashboard when you need an overview.'],
            'note'=>$mirror?'Your dashboard focuses on your own news, announcements and submission status.':'The dashboard shows the content and website activity available to your role.',
            'keywords'=>'home overview statistics counts recent activity shortcuts',
            'faqs'=>[['question'=>'Where can I check the public website?','answer'=>'Use the public-site shortcut at the top of the admin panel. It opens the website in another tab so you can check published content without leaving your work. Drafts and pending proposals are not public.']],
        ],
        '/admin/announcement.php'=>[
            'id'=>'announcements','icon'=>'megaphone','summary'=>'Prepare a clear announcement and check it before publishing.','cta'=>'Create or review announcements',
            'steps'=>['Choose New Announcement in Announcements.','Enter the title and message. Review the available category, audience, image and date fields.','Check names, dates and links before saving.',$super?'Choose a draft or published status as appropriate, then save. Publishing makes it visible to visitors.':'Save a draft to continue later, or submit the proposal for Super Admin approval.','Wait for the success message before leaving the form.'],
            'note'=>$mirror?'You can edit only your own announcements. Submitted changes remain private until approval.':($super?'Check the public website after publishing.':'Follow submitted changes in My Submissions. Saving a draft does not publish it.'),
            'keywords'=>'create add new announcement publish posting notice edit update save draft message',
            'faqs'=>[['question'=>'How do I create and publish an announcement?','answer'=>$super?'Choose New Announcement, complete and review the form, then use the published status when it is ready. Check the success message and the public website.':'Choose New Announcement, complete and review the form, then submit it for approval. A Super Admin must approve it before visitors can see it.']],
        ],
        '/admin/news.php'=>[
            'id'=>'news','icon'=>'newspaper','summary'=>'Write articles, add images and send content through review.','cta'=>'Write or review news articles',
            'steps'=>['Choose New Article in News.','Add a title, article text and relevant images. Check names, dates and links.',$super?'Save a draft while you are working, or choose the published status when the article is ready.':'Save a draft or submit the article for Super Admin approval.','Check the confirmation message after saving.'],
            'note'=>$mirror?'You can edit only your own articles. Pending articles are not on the public website.':($super?'Review published changes using the public-site shortcut.':'Track the proposal and any reviewer feedback in My Submissions.'),
            'keywords'=>'article create add edit publish news story images photos draft',
            'faqs'=>[['question'=>'Why can I not see my article on the website?','answer'=>'A draft is saved work and is not public. A pending submission is still awaiting approval. Check the article status and the save confirmation; if it requires approval, check its status in your submissions.']],
        ],
        '/admin/events.php'=>[
            'id'=>'events','title'=>'Events & Calendar','icon'=>'calendar-event','summary'=>'Add individual events or review a calendar import.','cta'=>'Plan or import calendar events',
            'steps'=>['Choose Add Event, or select a date on the calendar.','Enter the event title, start and end dates, location and other available details.','Check the dates, times and audience before saving.',$super?'Publish only when the event is ready for visitors.':'Submit your changes for Super Admin approval.'],
            'note'=>'For a calendar import, review the extracted activities and warnings before confirming. Opening an import preview does not publish it.',
            'keywords'=>'calendar import upload word document events dates schedule location add',
            'faqs'=>[['question'=>'How do I import a calendar?','answer'=>'Choose Import Calendar and upload a supported document using the instructions in the import form. Review extracted activities, correct dates or details, and resolve warnings. Continue through verification and the final summary before confirming. '.($super?'Only confirm the reviewed activities you intend to publish.':'The reviewed request still needs Super Admin approval before it changes the website.')]],
        ],
        '/admin/facebook-posts.php'=>[
            'id'=>'facebook','icon'=>'facebook','summary'=>'Find imported Facebook posts and check their original source.','cta'=>'Browse imported Facebook posts',
            'steps'=>['Search Facebook Posts for the post you need.','Open its original Facebook link to check the source.',$super?'Use the available category and pin controls when organizing imported posts.':'Browse the posts without changing their categories or pin settings.'],
            'note'=>$super?'These controls organize imported posts on the website. Check the original post when its source matters.':'Category and pin controls are restricted to Super Admin. These posts are read only for your role.',
            'keywords'=>'facebook social media imported source post pin category',
            'faqs'=>[['question'=>'Does editing a website article automatically publish it to Facebook?','answer'=>'Do not assume a website change creates a Facebook post. Website publishing and the available Facebook publishing controls are separate. Check the destination and confirmation shown by the relevant form.']],
        ],
        '/admin/content-edit.php'=>[
            'id'=>'content-editor','icon'=>'layout-text-window','summary'=>'Edit website pages while keeping drafts separate from published content.','cta'=>'Choose a page to edit',
            'steps'=>['Choose the website page in Content Editor.','Select editable text or pictures in the preview and make your changes.','Save your draft to keep work in progress. Preview and review it before continuing.',$super?'Publish when ready, then check the public page.':'Submit saved changes for Super Admin approval.'],
            'note'=>'Keep the editor open if saving fails. Correct the highlighted fields and wait for a success message before leaving unsaved work.',
            'keywords'=>'edit existing website page content text picture image visual editor preview save draft changes',
            'faqs'=>[['question'=>'How do I edit existing website content?','answer'=>'Choose a page in Content Editor and select the editable text or image in its preview. Save and check the proposed changes. '.($super?'Use the existing publish controls when ready.':'Submit the saved draft for approval; saving alone does not update the public page.')]],
        ],
        '/admin/submissions.php'=>[
            'id'=>'submissions','icon'=>'inboxes','summary'=>$super?'Review proposals and give authors a clear next step.':'Track proposals and respond to reviewer feedback.','cta'=>$super?'Review pending submissions':'Check your submissions',
            'steps'=>$super?['Choose a status card or search and filter the review queue. Pending proposals appear oldest first by default.','Select Review submission to read the proposal. Compare changes with current content where available.','Choose Approve and apply when ready. For saved page changes, review and approve in the Content Editor.','Use Request revision for corrections, or Reject submission to decline a proposal. Write clear feedback and confirm the decision.']:['Find your proposal in My Submissions. Use search or filters to narrow the queue.','Select Review submission to read its status and reviewer feedback.','Use Revise submission when available, or open saved page changes in Content Editor.','Save the corrected proposal and submit it for approval again.'],
            'note'=>$super?'Delete submission removes the saved proposal; it is separate from rejecting it and does not delete published website content.':'Pending means the proposal has not changed the public website. Needs revision and Rejected are separate review outcomes; read the feedback before resubmitting.',
            'keywords'=>'approve approvals approval rejected rejection needs revision request review feedback resubmit pending submission publish',
            'faqs'=>[['question'=>'Why does content require approval?','answer'=>'Proposals from Content Editors and The Mirror are saved separately from public content. A Super Admin reviews and approves them before they change the website. This includes edits as well as new content.'],['question'=>'What is the difference between Request revision and Reject?','answer'=>'Request revision asks the author to correct a proposal before another review. Reject declines the proposal and includes feedback. Both leave the public website unchanged. Read the feedback before deciding what to change and resubmit.']],
        ],
        '/admin/users.php'=>[
            'id'=>'users','title'=>'User Management','icon'=>'people','summary'=>'Create accounts and assign the access each person needs.','cta'=>'Manage admin accounts',
            'steps'=>['Enter the new user’s name and an email address they can access.','Choose the appropriate role, then create the account.','Copy the temporary password while it is displayed and share it privately with the account owner.','The new user verifies their email and changes the temporary password before using admin features.'],
            'note'=>'Review role changes carefully. Do not share temporary passwords or verification codes in public messages.',
            'keywords'=>'users accounts role permission access create add admin temporary password invite',
            'faqs'=>[['question'=>'Why can I not find the temporary password again?','answer'=>'The password is displayed only when the account is created. Ask the user to use Forgot Password on the login page if it was not saved or they cannot sign in.']],
        ],
        '/admin/logs.php'=>[
            'id'=>'activity-logs','icon'=>'clock-history','summary'=>'Find out who performed an action and when it happened.','cta'=>'Search activity history',
            'steps'=>['Choose Activity or Login & security.','Search by name, email, action or description. Use the action filter or More filters for user, module and date range.','Choose Apply filters. Adjust rows per page and order when needed.','Select View details to read the full description and exact timestamp.'],
            'note'=>'Activity history is read only. Use Clear filters to return to the full history. Do not share private account or security information.',
            'keywords'=>'activity audit logs history login security search filter date action user who changed',
            'faqs'=>[['question'=>'Can I change or remove an activity record?','answer'=>'The Activity Logs interface is read only. Search and filtering change only what is displayed; they do not edit or delete records.']],
        ],
        '/admin/settings.php'=>[
            'id'=>'account-security','title'=>'Account & Security','icon'=>'shield-lock','summary'=>'Change your password and keep your account secure.','cta'=>'Review account security',
            'steps'=>['Open Settings and find Account Security.','Choose Change Password and enter your current password.','Enter and confirm a new, unique password, then save.','On a shared device, select Forget this device when signing out.'],
            'note'=>'A successful password change revokes other sessions and remembered devices. '.(hasPermission('manage_settings')?'Website settings are also available in Settings; review each option before saving.':'Website-wide settings are restricted to roles with permission.'),
            'keywords'=>'account security password reset forgot change sign out logout remembered device verification email settings',
            'faqs'=>[['question'=>'How do I reset a forgotten password?','answer'=>'Choose Forgot Password on the login page and follow the email instructions. If you can sign in and know your current password, use Settings → Account Security → Change Password instead.'],['question'=>'What should I do if saving fails?','answer'=>'Keep the page open, read the error message and correct any highlighted fields. Check for a success message before leaving. If your session expired, sign in again; contact your Super Admin if the problem continues.']],
        ],
    ];
    foreach(adminOnboardingGuides() as $guide){
        if(!isset($details[$guide['route']]))continue;
        $topics[]=array_merge(['title'=>$guide['title'],'route'=>$guide['route']],$details[$guide['route']]);
    }
    return $topics;
}

function adminHelpSearch(array $topics,string $query): array {
    $words=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($query),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $stop=['how','can','i','to','do','the','a','an','my','is','why','does','what','with','on','in','of','and','it','me','you','for','we'];
    $words=array_values(array_diff($words,$stop));
    $results=[];
    foreach($topics as $topic){
        $text=mb_strtolower($topic['title'].' '.$topic['summary'].' '.implode(' ',$topic['steps']).' '.$topic['note'].' '.$topic['keywords']);
        foreach($topic['faqs'] as $faq)$text.=' '.mb_strtolower($faq['question'].' '.$faq['answer']);
        $score=0;
        foreach($words as $word){
            if(mb_strpos($text,$word)===false)continue 2;
            $score+=mb_stripos($topic['title'],$word)!==false?6:(mb_stripos($topic['summary'],$word)!==false?2:1);
        }
        if($words)$results[]=['topic'=>$topic,'score'=>$score];
    }
    usort($results,fn($a,$b)=>$b['score']<=>$a['score']);
    return array_column($results,'topic');
}
