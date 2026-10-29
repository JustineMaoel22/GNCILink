/** Deploy as the form owner. Set Script Properties FORM_ID, RECEIPTS_SHEET_ID,
 * FEEDBACK_SCRIPT_SECRET; see docs/visitor-feedback.md. Never put secrets here. */
function doPost(e) {
  let lock;
  try {
    if (!e || !e.postData || e.postData.contents.length > 8192) return feedbackJson_({ok:false});
    const properties = PropertiesService.getScriptProperties();
    const secret = properties.getProperty('FEEDBACK_SCRIPT_SECRET') || '';
    const envelope = JSON.parse(e.postData.contents);
    if (secret.length < 32 || typeof envelope.payload !== 'string' || !/^[a-f0-9]{64}$/.test(envelope.signature || '')) return feedbackJson_({ok:false});
    const expected = Utilities.computeHmacSha256Signature(envelope.payload, secret).map(n => ('0'+((n+256)%256).toString(16)).slice(-2)).join('');
    let different = 0;
    for (let i=0;i<64;i++) different |= expected.charCodeAt(i)^envelope.signature.charCodeAt(i);
    if (different) return feedbackJson_({ok:false});
    const request = JSON.parse(envelope.payload);
    if (!Number.isInteger(request.at) || Math.abs(Date.now()/1000-request.at)>300 || !/^[a-f0-9]{64}$/.test(request.visitor || '') || !['status','submit'].includes(request.action)) return feedbackJson_({ok:false});
    lock = LockService.getScriptLock();
    if (!lock.tryLock(10000)) return feedbackJson_({ok:false});
    const sheet = SpreadsheetApp.openById(properties.getProperty('RECEIPTS_SHEET_ID')).getSheetByName('Website receipts');
    if (!sheet || sheet.getRange(1,1,1,4).getValues()[0].join('|') !== 'visitor_hash|state|response_id|created_at') return feedbackJson_({ok:false});
    const found = sheet.getLastRow()>1 ? sheet.getRange(2,1,sheet.getLastRow()-1,1).createTextFinder(request.visitor).matchEntireCell(true).findNext() : null;
    if (found) {
      const row = sheet.getRange(found.getRow(),1,1,4).getValues()[0];
      if (row[1] === 'confirmed' && row[2]) return feedbackJson_({ok:true,visitor:request.visitor,submitted:true,receipt:String(row[2])});
      // A pending/uncertain Google write must never be retried as a second response.
      return feedbackJson_({ok:true,visitor:request.visitor,submitted:false,pending:true});
    }
    const form = FormApp.openById(properties.getProperty('FORM_ID'));
    const questions = feedbackQuestions_(form);
    const result = {ok:true,visitor:request.visitor,submitted:false,questions:questions.map(q=>({id:q.id,title:q.title,section:q.section}))};
    if (request.action === 'status') return feedbackJson_(result);
    if (!form.isAcceptingResponses() || !request.answers || typeof request.answers !== 'object' || Array.isArray(request.answers)) return feedbackJson_({ok:false});
    const ids = Object.keys(request.answers);
    if (ids.length!==questions.length || questions.some(q=>!Object.prototype.hasOwnProperty.call(request.answers,q.id) || !Number.isInteger(request.answers[q.id]) || request.answers[q.id]<1 || request.answers[q.id]>5) || ids.some(id=>!questions.some(q=>q.id===id))) return feedbackJson_({ok:false});
    let response = form.createResponse();
    questions.forEach(q=>response.withItemResponse(q.item.createResponse(request.answers[q.id])));
    // Persist the reservation before contacting Forms. A crash is recoverable by
    // the owner without risking an automatic duplicate after a timeout/retry.
    const rowNumber = sheet.getLastRow()+1;
    sheet.getRange(rowNumber,1,1,4).setValues([[request.visitor,'pending','',new Date().toISOString()]]);
    SpreadsheetApp.flush();
    response = response.submit();
    const receipt = response.getId();
    if (!receipt) return feedbackJson_({ok:false});
    sheet.getRange(rowNumber,2,1,2).setValues([['confirmed',receipt]]);
    SpreadsheetApp.flush();
    return feedbackJson_({ok:true,visitor:request.visitor,submitted:true,receipt:String(receipt)});
  } catch (_) {
    return feedbackJson_({ok:false});
  } finally { if (lock && lock.hasLock()) lock.releaseLock(); }
}

function feedbackQuestions_(form) {
  let section = '';
  const questions = [];
  form.getItems().forEach(item=>{
    const type = item.getType();
    if (type === FormApp.ItemType.SECTION_HEADER) { section=item.getTitle();return; }
    if (type === FormApp.ItemType.IMAGE || type === FormApp.ItemType.VIDEO) return;
    let rating;
    if (type === FormApp.ItemType.RATING) {
      rating=item.asRatingItem();
      if (rating.getRatingScaleLevel()!==5) throw Error('Use five-point ratings.');
    } else if (type === FormApp.ItemType.SCALE) {
      rating=item.asScaleItem();
      if (rating.getLowerBound()!==1 || rating.getUpperBound()!==5) throw Error('Use a 1 to 5 scale.');
    } else { throw Error('Unsupported question type.'); }
    questions.push({id:String(item.getId()),title:item.getTitle(),section,item:rating});
  });
  if (questions.length!==6) throw Error('Expected six rating questions.');
  return questions;
}

/** Run once manually, using a NEW empty spreadsheet selected by the owner. */
function setupFeedbackReceipts() {
  const properties=PropertiesService.getScriptProperties();
  const file=SpreadsheetApp.openById(properties.getProperty('RECEIPTS_SHEET_ID'));
  if (file.getSheetByName('Website receipts')) throw Error('Receipts sheet already exists; nothing changed.');
  const sheet=file.insertSheet('Website receipts');
  sheet.getRange(1,1,1,4).setValues([['visitor_hash','state','response_id','created_at']]);
  sheet.setFrozenRows(1);
}

function feedbackJson_(value) { return ContentService.createTextOutput(JSON.stringify(value)).setMimeType(ContentService.MimeType.JSON); }
