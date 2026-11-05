<?php
/** Read existing workflow records without changing their review state. */
function adminNotifications(): array {
    $items=[];$unavailable=false;
    if(hasPermission('manage_approvals')||hasPermission('view_own_submissions')){
        try{
            $super=rbacIsSuper();
            $where=$super?"status='pending'":"user_id=? AND status IN ('approved','rejected','needs_revision','failed','uncertain')";
            $query=getDB()->prepare('SELECT submission_id,title,status,updated_at FROM rbac_submissions WHERE '.$where.' ORDER BY updated_at DESC,submission_id DESC LIMIT 15');
            $query->execute($super?[]:[(int)$_SESSION['user_id']]);
            $labels=['pending'=>'Awaiting your review','approved'=>'Submission approved','rejected'=>'Submission rejected','needs_revision'=>'Revision requested','failed'=>'Submission could not be applied','uncertain'=>'Submission needs verification'];
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
                $items[]=['key'=>'submission-'.$row['submission_id'].'-'.$row['status'].'-'.$row['updated_at'],'title'=>$labels[$row['status']],'detail'=>$row['title'],'date'=>$row['updated_at'],'url'=>'/admin/submissions.php?id='.(int)$row['submission_id']];
            }
        }catch(Throwable $exception){$unavailable=true;error_log('Admin submission notifications unavailable.');}
    }
    if(hasPermission('approve_content')&&function_exists('pageReviewReady')&&pageReviewReady()){
        try{
            $count=(int)getDB()->query("SELECT COUNT(*) FROM page_sections WHERE review_status='pending'")->fetchColumn();
            if($count>0)$items[]=['key'=>'page-review-'.$count,'title'=>'Website text awaiting review','detail'=>$count.' page sections need approval.','date'=>'','url'=>'/admin/content-edit.php?page=review'];
        }catch(Throwable $exception){$unavailable=true;error_log('Admin page notifications unavailable.');}
    }
    return ['items'=>$items,'unavailable'=>$unavailable];
}
