<?php
require_once __DIR__.'/calendar-docx.php';
require_once __DIR__.'/calendar-import-workspace.php';
require_once __DIR__.'/event-audience.php';

/** Reuse the normal Events audience rules, including department/program membership. */
function calendarImportAudience(array $row, array $previous = []): array {
    $selection = array_key_exists('dept_id',$row) ? eventDepartmentSelection($row) : eventDepartmentSelection($previous);
    $program = $row['program'] ?? ($previous['program'] ?? 'ALL');
    $multiple = array_key_exists('dept_ids', $row) || array_key_exists('programs', $row);
    if (!is_string($program) || (!$multiple && $program!=='ALL' && !isset(getAnnouncementProgramCategories()[$program]))) throw new InvalidArgumentException('Choose College or Graduate School programs only. For Basic Education, select its departments.');
    if (is_string($selection) && str_starts_with($selection, 'group:') && (!$multiple || (($row['dept_ids']??null)===[] && ($row['programs']??null)===[]))) {
        return normalizeEventAudience($selection, $program) + ['dept_ids'=>[], 'programs'=>[]];
    }
    $rawDepartments = array_key_exists('dept_ids',$row) ? $row['dept_ids'] : ($selection ? [$selection] : []);
    $rawPrograms = array_key_exists('programs',$row) ? $row['programs'] : ($program !== 'ALL' ? [$program] : []);
    if (!$multiple) {
        if ($selection === eventDepartmentSelection($previous) && isset($previous['dept_ids'])) $rawDepartments = $previous['dept_ids'];
        if ($program === ($previous['program'] ?? 'ALL') && isset($previous['programs'])) $rawPrograms = $previous['programs'];
    }
    if (!is_array($rawDepartments) || !is_array($rawPrograms) || count($rawDepartments)>100 || count($rawPrograms)>200) throw new InvalidArgumentException('Choose valid departments and programs.');
    $deptIds=[];
    foreach ($rawDepartments as $id) {
        if ((!is_string($id)&&!is_int($id)) || !preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id)) throw new InvalidArgumentException('Choose valid departments.');
        $deptIds[]=(int)$id;
    }
    $deptIds=array_values(array_unique($deptIds));
    if ($deptIds) {
        if (!eventDepartmentsReady()) throw new InvalidArgumentException('Department targeting needs the Events department migration.');
        $q=getDB()->prepare('SELECT COUNT(*) FROM departments WHERE dept_id IN ('.implode(',',array_fill(0,count($deptIds),'?')).')');$q->execute($deptIds);
        if ((int)$q->fetchColumn()!==count($deptIds)) throw new InvalidArgumentException('Choose available departments.');
    }
    $codes=[];$validPrograms=getAnnouncementProgramCategories();
    foreach ($rawPrograms as $code) {
        if (!is_string($code) || $code==='ALL' || !isset($validPrograms[$code])) throw new InvalidArgumentException('Choose College or Graduate School programs only. For Basic Education, select its departments.');
        $codes[]=$code;
    }
    $codes=array_values(array_unique($codes));
    if ($deptIds && $codes) {
        $q=getDB()->prepare('SELECT dept_id FROM programs WHERE program_code=?');
        foreach ($codes as $code) {$q->execute([$code]);$department=$q->fetchColumn();if($department===false||!in_array((int)$department,$deptIds,true))throw new InvalidArgumentException('Choose programs belonging to the selected departments.');}
    }
    if ((count($deptIds)>1||count($codes)>1) && !calendarImportMultiAudienceReady(getDB())) throw new InvalidArgumentException('Run database/multi-audience-migration.sql to enable multiple departments and programs.');
    return ['dept_ids'=>$deptIds,'programs'=>$codes,'dept_id'=>count($deptIds)===1?$deptIds[0]:null,'program'=>count($codes)===1?$codes[0]:'ALL','audience_group'=>'all'];
}

function calendarImportMultiAudienceReady(PDO $db): bool {
    try {$db->query('SELECT event_id FROM event_departments LIMIT 0');$db->query('SELECT event_id FROM event_programs LIMIT 0');return true;}
    catch(PDOException $e){if($e->getCode()==='42S02')return false;throw $e;}
}

/** Use existing audience tables without DDL or swallowed errors inside the import transaction. */
function calendarImportSaveAudience(PDO $db,int $id,array $audience): void {
    if (!calendarImportMultiAudienceReady($db)) return;
    $db->prepare('DELETE FROM event_departments WHERE event_id=?')->execute([$id]);
    $db->prepare('DELETE FROM event_programs WHERE event_id=?')->execute([$id]);
    $q=$db->prepare('INSERT INTO event_departments(event_id,dept_id) VALUES(?,?)');
    foreach($audience['dept_ids'] as $department)$q->execute([$id,$department]);
    $q=$db->prepare('INSERT INTO event_programs(event_id,program_code) VALUES(?,?)');
    foreach($audience['programs'] as $code)$q->execute([$id,$code]);
}

/** Match stored department names, compact slugs, derived acronyms, audience groups, and program categories; never guess between conflicting matches. */
function calendarImportDetectDepartments(array $rows, array $departments, array $programs = [], bool $audienceGroupsReady = false): array {
    $lower=static fn(string $s)=>function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);
    $len=static fn(string $s)=>function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);
    $sub=static fn(string $s,int $start,int $length)=>function_exists('mb_substr')?mb_substr($s,$start,$length,'UTF-8'):substr($s,$start,$length);
    $normalize=static fn(string $value)=>trim((string)(preg_replace('/[^\p{L}\p{N}]+/u',' ',$lower($value))??$lower($value)));

    $deptById=[];
    $aliases=[];
    foreach($departments as $department) {
        $id=(int)$department['dept_id'];
        $deptById[$id]=$department;
        $name=$normalize($department['dept_name']??'');
        $words=explode(' ',$name);
        $acronym='';
        $acronymWithAnd='';
        foreach($words as $word) {
            if($word==='')continue;
            if(!in_array($word,['of','and','the','in','for'],true))$acronym.=$sub($word,0,1);
            if(!in_array($word,['of','the','in','for'],true))$acronymWithAnd.=$sub($word,0,1);
        }
        $names=[$name];
        if($len($acronym)>=2&&$len($acronym)<=10)$names[]=$acronym;
        if($acronymWithAnd!==$acronym&&$len($acronymWithAnd)>=2&&$len($acronymWithAnd)<=10)$names[]=$acronymWithAnd;
        $slug=$normalize($department['slug']??'');
        if($slug!==''&&$len($slug)>=2)$names[]=$slug;

        // Add "<field> department" and "<field> college" for "College of <field>"
        if(str_starts_with($name,'college of ')) {
            $field=trim(substr($name,11));
            if($field!=='') {
                $names[]=$field.' department';
                $names[]=$field.' college';
            }
        }

        // Known GNC department keyword synonyms & abbreviations
        if($slug==='cased'||str_contains($name,'arts and sciences')) {
            array_push($names,'cased','college of arts and sciences','college of education','arts and sciences','teacher education','education department','education days','education day','education students');
        } elseif($slug==='cba'||str_contains($name,'business administration')) {
            array_push($names,'cba','college of business','business administration department','business department','bsba department','cba day','cba days');
        } elseif($slug==='coe'||str_contains($name,'engineering')) {
            array_push($names,'coe','engineering department','engineering days','engineering day');
        } elseif($slug==='camp'||str_contains($name,'medical laboratory science')) {
            array_push($names,'camp','cmls','cmlsp','college of allied medical professions','allied medical professions','medical laboratory science and pharmacy','medtech and pharmacy','stamp days','stamp day');
        } elseif($slug==='coa'||str_contains($name,'accountancy')) {
            array_push($names,'coa','accountancy department','accounting department','accountancy days','accountancy day');
        } elseif($slug==='conursing'||str_contains($name,'nursing')) {
            array_push($names,'con','conursing','nursing department','school of nursing','nursing students','nurses day','nursing days','nursing day');
        } elseif($slug==='graduate-school'||$name==='graduate school') {
            array_push($names,'graduate school','grad school','graduate studies','postgraduate','post graduate');
        } elseif($slug==='casa-gradeschool'||str_contains($name,'casa')) {
            array_push($names,'casa','advance casa','junior advance casa','grade school montessori','elementary montessori','montessori grade school','casa and grade school','grade 1 montessori','grade 2 montessori','grade 3 montessori','grade 4 montessori','grade 5 montessori','grade 6 montessori','grade 1 and 2 montessori','grades 1 and 2 montessori','grade 2 and 3 montessori','grades 2 and 3 montessori','grade 1 3 montessori','grades 1 3 montessori','grade 4 6 montessori','grades 4 6 montessori');
        } elseif($slug==='hs-monte'||str_contains($name,'high school montessori')) {
            array_push($names,'high school montessori','montessori high school','hs montessori','hs monte','montessori promenade');
        } elseif($slug==='jhs-regular'||str_contains($name,'junior high')) {
            array_push($names,'jhs','junior high school','junior high','jhs regular','jhs department','high school regular','grade 7','grade 8','grade 9','grade 10','grades 9 and 10');
        } elseif($slug==='shs-regular'||str_contains($name,'senior high')) {
            array_push($names,'shs','senior high school','senior high','shs regular','shs reg','shs department','grades 11 and 12','grade 11 and 12');
        } elseif($slug==='college-of-computing'||str_contains($name,'computing')) {
            array_push($names,'ccs','coc','college of computer studies','computer studies','computing department','it department','ict department');
        } elseif($slug==='college-of-criminology'||str_contains($name,'criminology')) {
            array_push($names,'cocrim','ccje','criminology department','criminal justice','college of criminal justice education');
        }

        $aliases[$id]=array_values(array_unique(array_filter($names,static fn($n)=>$n!=='')));
    }

    // Resolve canonical department ID for each program from $programs or fallback department matching
    $findDeptByHint=static function(array $slugHints) use ($departments,$normalize): ?int {
        foreach($slugHints as $hint) {
            $nHint=$normalize($hint);
            foreach($departments as $dept) {
                $dSlug=$normalize($dept['slug']??'');
                $dName=$normalize($dept['dept_name']??'');
                if($dSlug===$nHint||$dName===$nHint||($nHint!==''&&str_contains($dName,$nHint)))return (int)$dept['dept_id'];
            }
        }
        return null;
    };

    $programCatalog=[
        'BEEd'=>['hints'=>['cased','arts and sciences'],'aliases'=>['beed','bachelor of elementary education','elementary education'],'short'=>['elementary education']],
        'BSEd'=>['hints'=>['cased','arts and sciences'],'aliases'=>['bsed','bse','bachelor of secondary education','secondary education'],'short'=>['secondary education']],
        'BAEL'=>['hints'=>['cased','arts and sciences'],'aliases'=>['bael','abel','ab english','ba english','bachelor of arts in english language','bachelor of arts in english','english language studies','english language program'],'short'=>['english language','english']],
        'BSCE'=>['hints'=>['coe','engineering'],'aliases'=>['bsce','bs civil engineering','bachelor of science in civil engineering','civil engineering'],'short'=>['civil engineering']],
        'BSAIS'=>['hints'=>['coa','accountancy'],'aliases'=>['bsais','bsact','bs accounting information systems','bs accounting information system','bachelor of science in accounting information systems','bachelor of science in accounting information system','accounting information systems','accounting information system','accounting technology'],'short'=>['accounting information systems','accounting information system']],
        'BSA'=>['hints'=>['coa','accountancy'],'aliases'=>['bsa','bs accountancy','bachelor of science in accountancy'],'short'=>['accountancy']],
        'BSBA-FM'=>['hints'=>['cba','business administration'],'aliases'=>['bsba fm','bsba','bsba financial management','bs business administration','bachelor of science in business administration','bachelor of science in business administration major in financial management','financial management'],'short'=>['business administration','financial management']],
        'BSCS'=>['hints'=>['college-of-computing','computing','cba'],'aliases'=>['bscs','bs computer science','bachelor of science in computer science','computer science'],'short'=>['computer science']],
        'BSIT'=>['hints'=>['college-of-computing','computing','cba'],'aliases'=>['bsit','bs information technology','bachelor of science in information technology','information technology'],'short'=>['information technology']],
        'BSHM'=>['hints'=>['cba','business administration'],'aliases'=>['bshm','bshrm','bshm tm','bstm hm','bs hospitality management','bachelor of science in hospitality management','hospitality management','hotel and restaurant management'],'short'=>['hospitality management','hospitality']],
        'BSTM'=>['hints'=>['cba','business administration'],'aliases'=>['bstm','bshm tm','bstm hm','bs tourism management','bs tourism','bachelor of science in tourism management','tourism management','tourism day'],'short'=>['tourism management','tourism']],
        'BSMLS'=>['hints'=>['camp','medical laboratory science'],'aliases'=>['bsmls','bsmt','bs medical laboratory science','bs medtech','bs medical technology','bachelor of science in medical laboratory science','bachelor of science in medical technology','medical laboratory science','medical technology','medtech'],'short'=>['medical laboratory science','medical technology','medtech']],
        'BSPh'=>['hints'=>['camp','medical laboratory science'],'aliases'=>['bsph','bspha','bs pharmacy','bachelor of science in pharmacy','pharmacy program','pharmacy students','pharmacy week','pharmacy day','pharmacy orientation'],'short'=>['pharmacy']],
        'BSN'=>['hints'=>['conursing','nursing'],'aliases'=>['bsn','bs nursing','bachelor of science in nursing'],'short'=>['nursing']],
        'BSCRIM'=>['hints'=>['college-of-criminology','criminology'],'aliases'=>['bscrim','bs criminology','bachelor of science in criminology'],'short'=>['criminology']],
        'GS-PHD'=>['hints'=>['graduate-school','graduate school'],'aliases'=>['gs phd','phd','ph d','edd','ed d','doctor of philosophy','doctor of education','doctoral program'],'short'=>['doctor of philosophy','doctoral']],
        'GS-MAED'=>['hints'=>['graduate-school','graduate school'],'aliases'=>['gs maed','maed','ma ed','master of arts in education','masters in education','educational management'],'short'=>['master of arts in education','educational management']],
        'GS-MBA'=>['hints'=>['graduate-school','graduate school'],'aliases'=>['gs mba','mba','master in business administration','masters in business administration','master of business administration'],'short'=>['master in business administration']],
        'GS-MPA'=>['hints'=>['graduate-school','graduate school'],'aliases'=>['gs mpa','mpa','master in public administration','masters in public administration','master of public administration','public administration'],'short'=>['master in public administration','public administration']],
    ];

    $progDeptMap=[];
    $hasExplicitPrograms=!empty($programs);
    foreach($programs as $prog) {
        $code=(string)($prog['code']??$prog['program_code']??'');
        if($code===''||$code==='ALL')continue;
        $dId=isset($prog['dept_id'])&&$prog['dept_id']!==''&&$prog['dept_id']!==null?(int)$prog['dept_id']:null;
        $progDeptMap[$code]=($dId!==null&&isset($deptById[$dId]))?$dId:null;
        $pName=$normalize((string)($prog['name']??$prog['program_name']??''));
        $nCode=$normalize($code);
        if(!isset($programCatalog[$code])) {
            $programCatalog[$code]=['hints'=>[],'aliases'=>array_values(array_filter([$nCode,$pName])),'short'=>[]];
        } else {
            if($nCode!==''&&!in_array($nCode,$programCatalog[$code]['aliases'],true))$programCatalog[$code]['aliases'][]=$nCode;
            if($pName!==''&&!in_array($pName,$programCatalog[$code]['aliases'],true))$programCatalog[$code]['aliases'][]=$pName;
        }
    }
    if(!$hasExplicitPrograms) {
        foreach($programCatalog as $code=>$info) {
            $progDeptMap[$code]=$findDeptByHint($info['hints']);
        }
    }

    $matchProgramsInText=static function(string $haystack, bool $includeShort) use ($programCatalog,$normalize): array {
        $norm=' '.$normalize($haystack).' ';
        if(trim($norm)==='')return ['codes'=>[],'masked'=>$norm];
        // Pre-mask full multi-program department names (e.g. "College of Medical Laboratory Science and Pharmacy") so they match the department rather than a single program
        $progSearch=$norm;
        if(!$includeShort) {
            foreach(['college of medical laboratory science and pharmacy','medical laboratory science and pharmacy','medtech and pharmacy','college of business administration'] as $deptPhrase) {
                $progSearch=str_replace(' '.$deptPhrase.' ',' ',$progSearch);
            }
        }
        $matched=[];
        $masked=$norm;
        foreach($programCatalog as $code=>$info) {
            $candidates=$includeShort?array_merge($info['aliases'],$info['short']):$info['aliases'];
            // Sort longer phrases first so full degree names are masked before shorter tokens
            usort($candidates,static fn(string $a,string $b)=>strlen($b)<=>strlen($a));
            foreach($candidates as $alias) {
                if($alias!==''&&str_contains($progSearch,' '.$alias.' ')) {
                    $matched[$code]=true;
                    if(str_contains($alias,' '))$masked=str_replace(' '.$alias.' ',' ',$masked);
                    break;
                }
            }
        }
        return ['codes'=>array_keys($matched),'masked'=>$masked];
    };

    $matchDepartmentsInText=static function(string $paddedNorm) use ($aliases): array {
        if(trim($paddedNorm)==='')return [];
        $matches=[];
        foreach($aliases as $id=>$names) {
            foreach($names as $name) {
                if($name!==''&&str_contains($paddedNorm,' '.$name.' ')) {
                    $matches[]=$id;
                    break;
                }
            }
        }
        return array_values(array_unique($matches));
    };

    $deptGroupOf=static function(int $dId) use ($deptById,$normalize): string {
        $dept=$deptById[$dId]??[];
        $dSlug=$normalize($dept['slug']??'');
        $dName=$normalize($dept['dept_name']??'');
        $dType=strtolower(trim((string)($dept['department_type']??'')));
        if($dSlug==='graduate school'||$dName==='graduate school')return 'graduate';
        if($dType==='basic-education'||in_array($dSlug,['casa gradeschool','hs monte','jhs regular','shs regular'],true)||preg_match('/\b(casa|montessori|junior high|senior high|grade school|elementary)\b/',$dName)) {
            return 'basic-education';
        }
        return 'college';
    };

    $collegeGroupPhrases=['all college','all colleges','different colleges','college wide','college department','college departments','college level','college students','college freshmen','college faculty','college orientation','college foundation day','college week','college days','college day','college intramurals','college assembly','collegiate','tertiary level','tertiary department','higher education'];
    $basicEdGroupPhrases=['all basic education','basic education','basic education group','basic ed','bed department','k to 12','k 12','preschool to senior high','elementary and high school','grade school and high school','jhs and shs montessori regular','senior high school regular and montessori','junior and senior high school regular and montessori','junior high school montessori and regular','senior high school montessori and regular'];

    foreach($rows as &$row) {
        $deptHint=trim((string)($row['department_hint']??''));
        $progHint=trim((string)($row['program_hint']??''));
        $fullText=trim(($row['title']??'').' '.($row['description']??''));
        $combinedNorm=' '.$normalize($deptHint.' '.$progHint.' '.$fullText).' ';

        // Multi-department Basic Education shorthand phrases (e.g. "JHS and SHS Montessori/Regular", "Senior High School Regular and Montessori")
        if($audienceGroupsReady) {
            if(str_contains($combinedNorm,' basic education group ') && (str_contains($combinedNorm,' different colleges ') || str_contains($combinedNorm,' all colleges '))) {
                $row['dept_id']=null;
                $row['program']='ALL';
                $row['audience_group']='all';
                continue;
            }
        }

        // 1. Detect program categories (check explicit program_hint first, then department_hint, then title/description)
        $progResult=['codes'=>[],'masked'=>' '.$normalize($fullText).' '];
        if($progHint!=='') {
            $progResult=$matchProgramsInText($progHint,true);
        }
        if(!$progResult['codes']&&$deptHint!=='') {
            $progResult=$matchProgramsInText($deptHint,false);
        }
        $bodyProgResult=$matchProgramsInText($fullText,false);
        if(!$progResult['codes']) {
            $progResult=$bodyProgResult;
        }
        $matchedPrograms=$progResult['codes'];

        // 2. Detect departments (check explicit department_hint first, then title/description with multi-word degree phrases masked)
        $matchedDepts=[];
        if($deptHint!=='') {
            $hintMask=$matchProgramsInText($deptHint,false)['masked'];
            $matchedDepts=$matchDepartmentsInText($hintMask);
        }
        if(!$matchedDepts) {
            $matchedDepts=$matchDepartmentsInText($bodyProgResult['masked']);
        }
        // If both a specific Basic Ed department (e.g. "High School Montessori" or "Junior High School Regular") and a generic grade ("Grade 7", "Grade 10") matched, keep the explicit Montessori/SHS department when the text specifically says "High School Montessori"
        if(count($matchedDepts)>1 && str_contains($combinedNorm,' high school montessori ') && !str_contains($combinedNorm,' regular ')) {
            $hsMonteIds=array_values(array_filter($matchedDepts,static fn(int $id)=>str_contains($normalize($deptById[$id]['dept_name']??''),'high school montessori')));
            if(count($hsMonteIds)===1)$matchedDepts=$hsMonteIds;
        }

        $row['dept_id']=null;
        $row['program']='ALL';

        if(count($matchedPrograms)===1) {
            $progCode=$matchedPrograms[0];
            $progDept=$progDeptMap[$progCode]??null;
            if(count($matchedDepts)>1) {
                $row['warnings'][]='Several departments match this activity. Choose its department before approving.';
            } elseif(count($matchedDepts)===1) {
                $explicitDept=$matchedDepts[0];
                if($progDept!==null&&$progDept===$explicitDept) {
                    $row['dept_id']=$progDept;
                    $row['program']=$progCode;
                } elseif(!$hasExplicitPrograms) {
                    $row['dept_id']=$explicitDept;
                    $row['program']=$progCode;
                } elseif($progDept!==null) {
                    if(in_array($progCode,['BSIT','BSCS'],true)&&str_contains($normalize($deptById[$explicitDept]['dept_name']??''),'business administration')) {
                        $row['dept_id']=$progDept;
                        $row['program']=$progCode;
                    } else {
                        // Explicit department and program belong to different departments
                        $row['warnings'][]='Several departments match this activity. Choose its department before approving.';
                    }
                } else {
                    $row['dept_id']=$explicitDept;
                    $row['program']='ALL';
                }
            } else {
                // Only the program was mentioned; auto-assign its parent department when available
                if($progDept!==null) {
                    $row['dept_id']=$progDept;
                    $row['program']=$progCode;
                } elseif(!$hasExplicitPrograms) {
                    // If no departments in $departments matched this program's hints, only set program when $departments is empty
                    if(!$departments)$row['program']=$progCode;
                } else {
                    $row['dept_id']=null;
                    $row['program']=$progCode;
                }
            }
        } elseif(count($matchedPrograms)>1) {
            // Check if all matched programs belong to the exact same department
            $progDepts=[];
            foreach($matchedPrograms as $pCode) {
                if(isset($progDeptMap[$pCode])&&$progDeptMap[$pCode]!==null)$progDepts[]=$progDeptMap[$pCode];
            }
            $progDepts=array_values(array_unique($progDepts));
            if(count($progDepts)===1&&(count($matchedDepts)===0||(count($matchedDepts)===1&&$matchedDepts[0]===$progDepts[0]))) {
                $row['dept_id']=$progDepts[0];
                $row['program']='ALL';
            } elseif($audienceGroupsReady) {
                $allCollege=true;
                foreach($progDepts as $pd) { if($deptGroupOf($pd)!=='college')$allCollege=false; }
                foreach($matchedDepts as $md) { if($deptGroupOf($md)!=='college')$allCollege=false; }
                if($allCollege && (!empty($progDepts)||!empty($matchedDepts))) {
                    $row['audience_group']='college';
                } else {
                    $row['warnings'][]='Several departments match this activity. Choose its department before approving.';
                }
            } else {
                $row['warnings'][]='Several departments match this activity. Choose its department before approving.';
            }
        } else {
            // No specific program matched; use department match
            if(count($matchedDepts)===1) {
                $row['dept_id']=$matchedDepts[0];
                // Still check if a multi-department Basic Ed phrase like "JHS and SHS Montessori/Regular" or "Senior High School Regular and Montessori" is present
                if($audienceGroupsReady) {
                    foreach(['jhs and shs montessori regular','senior high school regular and montessori','junior and senior high school regular and montessori','junior high school montessori and regular','senior high school montessori and regular'] as $multiBePhrase) {
                        if(str_contains($combinedNorm,' '.$multiBePhrase.' ')) {
                            $row['dept_id']=null;
                            $row['audience_group']='basic-education';
                            break;
                        }
                    }
                }
            } elseif(count($matchedDepts)>1) {
                if($audienceGroupsReady) {
                    $groups=array_values(array_unique(array_map($deptGroupOf,$matchedDepts)));
                    if(count($groups)===1 && in_array($groups[0],['basic-education','college'],true)) {
                        $row['audience_group']=$groups[0];
                    } else {
                        $row['warnings'][]='Several departments match this activity. Choose its department before approving.';
                    }
                } else {
                    $row['warnings'][]='Several departments match this activity. Choose its department before approving.';
                }
            }
        }

        // 3. Detect broad audience groups ("All College" / "All Basic Education") when no specific department or program matched
        if($audienceGroupsReady && $row['dept_id']===null && empty($row['audience_group']) && $row['program']==='ALL' && count($matchedDepts)===0 && count($matchedPrograms)===0) {
            // Avoid matching "college of <specific name>" as an All-College group when that college isn't in $departments
            $withoutCollegeOf=preg_replace('/\bcollege of [a-z]+/u',' ',$combinedNorm);
            $isCollegeGroup=false;
            $isBasicEdGroup=false;
            foreach($collegeGroupPhrases as $phrase) {
                if(str_contains($withoutCollegeOf,' '.$phrase.' ')) { $isCollegeGroup=true; break; }
            }
            foreach($basicEdGroupPhrases as $phrase) {
                if(str_contains($combinedNorm,' '.$phrase.' ')) { $isBasicEdGroup=true; break; }
            }
            if($isCollegeGroup && !$isBasicEdGroup) {
                $row['audience_group']='college';
            } elseif($isBasicEdGroup && !$isCollegeGroup) {
                $row['audience_group']='basic-education';
            }
        }
    }
    unset($row);
    return $rows;
}

function calendarImportReady(PDO $db): bool {
    try { $db->query('SELECT description FROM events LIMIT 0'); $db->query('SELECT token,calendar_action FROM calendar_import_batches LIMIT 0'); $db->query('SELECT status FROM calendar_import_series LIMIT 0'); return true; }
    catch (PDOException $e) { return false; }
}
function calendarImportCategories(PDO $db): array {
    return $db->query("SELECT category_id, category_name FROM categories WHERE category_name NOT IN ('Reminder','Job Opportunities','Scholarship') ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
}
function calendarEventSignature(array $event): string {
    $fields=[];
    foreach (['event_id','title','start_date','end_date','category_id','description','status','location','program','dept_id','audience_group','user_id','updated_at'] as $key) $fields[$key]=(string)($event[$key]??'');
    foreach(['dept_ids','programs'] as $key)if(isset($event[$key])){$values=$event[$key];sort($values);$fields[$key]=$values;}
    return hash('sha256',json_encode($fields,JSON_UNESCAPED_UNICODE));
}
function calendarImportExisting(PDO $db, int $series): array {
    $q=$db->prepare('SELECT e.* FROM events e JOIN calendar_import_sources s ON s.event_id=e.event_id WHERE s.series_id=? ORDER BY e.start_date');
    $q->execute([$series]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if($rows&&calendarImportMultiAudienceReady($db)){
        $ids=array_column($rows,'event_id');$audiences=[];
        foreach(['dept_ids'=>['event_departments','dept_id'],'programs'=>['event_programs','program_code']] as $key=>[$table,$column]){
            $q=$db->prepare("SELECT event_id,$column FROM $table WHERE event_id IN (".implode(',',array_fill(0,count($ids),'?')).") ORDER BY $column");$q->execute($ids);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $item)$audiences[$item['event_id']][$key][]=$key==='dept_ids'?(int)$item[$column]:$item[$column];
        }
        foreach($rows as &$row){
            if(($row['audience_group']??'all')!=='all')continue;
            $row['dept_ids']=$audiences[$row['event_id']]['dept_ids']??(!empty($row['dept_id'])?[(int)$row['dept_id']]:[]);
            $row['programs']=$audiences[$row['event_id']]['programs']??(!empty($row['program'])&&$row['program']!=='ALL'?[$row['program']]:[]);
        }unset($row);
    }
    return $rows;
}
function calendarTitleKey(string $title): string { return strtolower(trim((string)(preg_replace('/\s+/u',' ',$title)??$title))); }

function calendarImportCompare(array $rows, array $existing, array $all): array {
    $used=[]; $seen=[];
    foreach ($rows as &$row) {
        $matches=array_values(array_filter($existing,static fn($e)=>calendarTitleKey($e['title'])===calendarTitleKey($row['title'])));
        if (!$matches && $row['start_date']!=='') $matches=array_values(array_filter($existing,static fn($e)=>substr($e['start_date']??'',0,10)===$row['start_date']));
        if(count($matches)===1 && !isset($used[$matches[0]['event_id']])) {
            $event=$matches[0];$id=(int)$event['event_id'];$used[$id]=true;
            $row['event_id']=$id; $row['action']='update'; $row['category_id']=$event['category_id'];
            $row['category_confidence']='preserved'; $row['category_reason']='The current category has been kept for this revised activity.';
            $row['dept_id']=$event['dept_id']??null; $row['program']=$event['program']??'ALL';
            $row['audience_group']=$event['audience_group']??'all';
            foreach(['dept_ids','programs'] as $key)if(isset($event[$key]))$row[$key]=$event[$key];
            if(empty($row['location_provided'])&&empty($row['location']))$row['location']=$event['location']??'';
            $row['warnings']=array_values(array_filter($row['warnings'],static fn($w)=>$w!=='Category needs review.'));
            if (!empty($row['missing_fields'])) {
                $row['missing_fields']=array_values(array_filter($row['missing_fields'],static fn($f)=>$f!=='category_id'));
            }
            $row['warnings'][]='Revised activity: check the changes. The current category has been kept.';
            $row['previous']=$event;
        } elseif ($matches) $row['warnings'][]='Several existing activities may match. Choose the correct existing event or exclude this row.';
        $key=calendarTitleKey($row['title']).'|'.$row['start_date'];
        if(isset($seen[$key]))$row['warnings'][]='Possible duplicate inside this document.';
        $seen[$key]=true;
        foreach ($all as $event) {
            if((int)$event['event_id']===$row['event_id'])continue;
            if($row['title']!=='' && (calendarTitleKey($event['title'])===calendarTitleKey($row['title']) || ($row['start_date']!==''&&substr($event['start_date']??'',0,10)===$row['start_date']&&similar_text(calendarTitleKey($event['title']),calendarTitleKey($row['title']))>min(strlen($event['title']),strlen($row['title']))*0.75))) {
                $row['warnings'][]='Possible duplicate of existing event #'.$event['event_id'].': '.$event['title'];
            }
        }
    }
    unset($row);
    foreach($existing as $event) if(!isset($used[$event['event_id']])) {
        $rows[]=['title'=>$event['title'],'start_date'=>substr($event['start_date']??'',0,10),'end_date'=>substr($event['end_date']??'',0,10),'start_time'=>'','end_time'=>'','description'=>$event['description']??'','category_id'=>$event['category_id'],'event_id'=>(int)$event['event_id'],'action'=>'archive','warnings'=>['Possibly removed from this revision. Excluded by default; select only to hide it after approval.'],'missing_fields'=>[],'source'=>'Not matched in the revised document.','previous'=>$event];
    }
    return $rows;
}

function calendarImportValidate(array $edits, array $preview, array $existing, array $categories): array {
    if(count($edits)>1500)throw new InvalidArgumentException('Too many activities.');
    $existingById=array_column($existing,null,'event_id');$catIds=array_map('intval',array_column($categories,'category_id'));$out=[];$targets=[];$keys=[];
    $len=static fn(string $s)=>function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);
    foreach($edits as $index=>$edit) {
        if(!is_array($edit)||empty($edit['include']))continue;
        $hasSourceIndex = isset($edit['source_index']) && is_scalar($edit['source_index']) && ctype_digit((string)$edit['source_index']);
        $srcIdx = $hasSourceIndex ? (int)$edit['source_index'] : (is_int($index) ? $index : -1);
        if (!isset($preview[$srcIdx])) throw new InvalidArgumentException('The preview changed. Upload the document again.');
        $original = $preview[$srcIdx];
        if(!is_scalar($edit['event_id']??0)||!ctype_digit((string)($edit['event_id']??0)))throw new InvalidArgumentException('Choose a valid imported event target.');
        $id=(int)($edit['event_id']??0);
        if($id && (!isset($existingById[$id])||isset($targets[$id])))throw new InvalidArgumentException('Choose each existing imported event only once. Manually created events cannot be overwritten.');
        if($id)$targets[$id]=true;
        if(empty($edit['reviewed']))throw new InvalidArgumentException('Review and approve each selected activity.');
        $editAction = is_string($edit['action'] ?? null) ? $edit['action'] : ($original['action'] ?? 'create');
        $operation=$editAction==='archive'?'archive':($id?'update':'create');
        if($operation==='archive' && (!$original || ($original['action']??'')!=='archive' || $id!==(int)$original['event_id']))throw new InvalidArgumentException('Invalid removal target.');
        $row=['event_id'=>$id,'action'=>$operation,'signature'=>$id?calendarEventSignature($existingById[$id]):null];
        if($operation==='archive') { $row['title']=$existingById[$id]['title'];$row['start_date']=$existingById[$id]['start_date'];$row['end_date']=$existingById[$id]['end_date'];$row['category_id']=$existingById[$id]['category_id'];$row['location']=$existingById[$id]['location']??'';$out[]=$row;continue; }
        foreach(['title'=>500,'description'=>10000,'location'=>500,'start_date'=>10,'end_date'=>10,'start_time'=>5,'end_time'=>5] as $field=>$limit) {
            if(!is_scalar($edit[$field]??''))throw new InvalidArgumentException('Invalid event field.');
            $row[$field]=trim((string)($edit[$field]??($field==='location'&&$id?($existingById[$id]['location']??''):'')));
            if($len($row[$field])>$limit)throw new InvalidArgumentException('An event field is too long: '.$field);
        }
        if($row['title']===''||!calendarValidDate($row['start_date'])||($row['end_date']!==''&&!calendarValidDate($row['end_date'])))throw new InvalidArgumentException('Every selected activity needs a title and valid dates (1900–2050).');
        foreach(['start_time','end_time'] as $field) if($row[$field]!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$row[$field]))throw new InvalidArgumentException('Use valid 24-hour times.');
        $start=$row['start_date'].' '.($row['start_time']?:'00:00').':00';
        $end=($row['end_date']?:($row['end_time']!==''?$row['start_date']:''));
        $end=$end!==''?$end.' '.($row['end_time']?:'00:00').':00':null;
        if($end!==null&&$end<$start)throw new InvalidArgumentException('An activity ends before it starts. Correct the date or time.');
        $row['start_date']=$start;$row['end_date']=$end;
        $cat=$edit['category_id']??'';
        if($cat!==''&&$cat!==null&&(!is_scalar($cat)||!ctype_digit((string)$cat)||!in_array((int)$cat,$catIds,true)))throw new InvalidArgumentException('Choose an existing event category.');
        $row['category_id']=$cat!==''&&$cat!==null?(int)$cat:null;
        if($row['category_id']===null)throw new InvalidArgumentException('Choose a category for every selected activity. Exclude uncertain activities until they can be categorized.');
        $row += calendarImportAudience($edit, $id ? $existingById[$id] : []);
        $key=calendarTitleKey($row['title']).'|'.$start;
        if(isset($keys[$key]))throw new InvalidArgumentException('The selection contains duplicate events. Exclude the duplicate row.');
        $keys[$key]=true;$out[]=$row;
    }
    if(!$out)throw new InvalidArgumentException('Select at least one reviewed activity.');
    return $out;
}

function calendarImportApply(PDO $db, array $batch, array $rows, string $status, int $actor): void {
    if(!hasPermission('publish_event'))throw new InvalidArgumentException('Only an approver can apply calendar imports.');
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM calendar_import_series WHERE series_id=? FOR UPDATE');$q->execute([$batch['series_id']]);$series=$q->fetch(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT state FROM calendar_import_batches WHERE token=? FOR UPDATE');$q->execute([$batch['token']]);$state=$q->fetchColumn();
        if($state==='applied') { $db->commit();return; }
        if(!in_array($state,['ready','submitted'],true)||!$series||(int)$series['revision']!==(int)$batch['base_revision'])throw new InvalidArgumentException('This calendar has changed. Upload the revised document again to compare fresh records.');
        if($series['status']==='deleted')throw new InvalidArgumentException('This calendar has been deleted.');
        $calendarAction=$batch['calendar_action']??null;
        if($calendarAction==='delete'&&!hasPermission('delete_event'))throw new InvalidArgumentException('You do not have permission to delete calendar events.');
        if(!$calendarAction&&$series['status']!=='active')throw new InvalidArgumentException('This calendar is archived. Create a new calendar for future imports.');
        $cats=array_map('intval',array_column(calendarImportCategories($db),'category_id'));
        // Lock all events to serialize duplicate checks with other imports and manual updates.
        $all=$db->query('SELECT * FROM events FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $existing=array_column(calendarImportExisting($db,(int)$batch['series_id']),null,'event_id');
        if($calendarAction) {
            $expected=array_map('intval',array_column($rows,'event_id'));$actual=array_map('intval',array_keys($existing));sort($expected);sort($actual);
            if($expected!==$actual)throw new InvalidArgumentException('Calendar events changed since review. Open a fresh preview.');
        }
        foreach($rows as $row) {
            $id=(int)$row['event_id'];
            if($id && (!isset($existing[$id])||!hash_equals($row['signature'],calendarEventSignature($existing[$id]))))throw new InvalidArgumentException('An existing event changed since preview. Upload again before applying this import.');
            if($row['action']==='delete') {
                if($calendarAction!=='delete'||!$id)throw new InvalidArgumentException('Invalid calendar deletion.');
                if(calendarImportMultiAudienceReady($db)) {
                    $db->prepare('DELETE FROM event_departments WHERE event_id=?')->execute([$id]);
                    $db->prepare('DELETE FROM event_programs WHERE event_id=?')->execute([$id]);
                }
                $db->prepare('DELETE FROM calendar_import_sources WHERE event_id=? AND series_id=?')->execute([$id,$batch['series_id']]);
                $db->prepare('DELETE FROM events WHERE event_id=?')->execute([$id]);
                logActivity($actor,'DELETE','events',$id,'Deleted imported calendar event: '.$row['title']);continue;
            }
            if($row['action']==='archive') {
                $db->prepare("UPDATE events SET status='archived' WHERE event_id=?")->execute([$id]);
                logActivity($actor,'UPDATE','events',$id,'Archived event explicitly removed during calendar import.');continue;
            }
            if(!in_array((int)$row['category_id'],$cats,true))throw new InvalidArgumentException('An event category changed. Preview the document again.');
            foreach($all as $event) if((int)$event['event_id']!==$id&&calendarTitleKey($event['title'])===calendarTitleKey($row['title'])&&$event['start_date']===$row['start_date'])throw new InvalidArgumentException('An identical event already exists: '.$row['title'].'. Exclude it or update its imported record.');
            $audience=calendarImportAudience($row,$id?$existing[$id]:[]);
            $values=[$row['title'],$row['start_date'],$row['end_date'],$row['category_id'],$row['description'],$status,$row['location']??($id?($existing[$id]['location']??''):''),$audience['program']];
            $hasDepartments=eventDepartmentsReady();
            if($hasDepartments)$values[]=$audience['dept_id'];
            $hasGroups=eventAudienceGroupsReady();
            if($hasGroups)$values[]=$audience['audience_group'];
            if($id) {
                $values[]=$id;
                $db->prepare('UPDATE events SET title=?, start_date=?, end_date=?, category_id=?, description=?, status=?, location=?, program=?'.($hasDepartments?', dept_id=?':'').($hasGroups?', audience_group=?':'').' WHERE event_id=?')->execute($values);
            } else {
                $values[]=rbacContentOwnerId();
                $db->prepare('INSERT INTO events(title,start_date,end_date,category_id,description,status,location,program'.($hasDepartments?',dept_id':'').($hasGroups?',audience_group':'').',user_id) VALUES(?,?,?,?,?,?,?,?,'.($hasDepartments?'?,':'').($hasGroups?'?,':'').'?)')->execute($values);
                $id=(int)$db->lastInsertId();
                $db->prepare('INSERT INTO calendar_import_sources(event_id,series_id) VALUES(?,?)')->execute([$id,$batch['series_id']]);
            }
            $all[]=['event_id'=>$id,'title'=>$row['title'],'start_date'=>$row['start_date']];
            calendarImportSaveAudience($db,$id,$audience);
            logActivity($actor,$row['action']==='create'?'CREATE':'UPDATE','events',$id,'Calendar import: '.$row['title']);
            if($status==='published')logActivity($actor,'PUBLISH','events',$id,'Published reviewed calendar import.');
        }
        $db->prepare('UPDATE calendar_import_series SET revision=revision+1, status=? WHERE series_id=?')->execute([$calendarAction==='delete'?'deleted':($calendarAction==='archive'?'archived':'active'),$batch['series_id']]);
        $db->prepare("UPDATE calendar_import_batches SET state='applied' WHERE token=?")->execute([$batch['token']]);
        logActivity($actor,$calendarAction==='delete'?'DELETE':($calendarAction==='archive'?'ARCHIVE':'IMPORT'),'calendar_import_series',(int)$batch['series_id'],'Applied '.count($rows).' reviewed calendar operations.');
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
}

/** Called only after login and the existing Events CSRF check. */
function calendarImportHandle(PDO $db, string $action, int $userId): array {
    if(!hasPermission('create_event')||!hasPermission('edit_event')) { http_response_code(403);throw new InvalidArgumentException('You do not have permission to import calendars.'); }
    if(!calendarImportReady($db))throw new InvalidArgumentException('Calendar import storage is not ready. Ask the developer to apply database/calendar-import-migration.sql.');
    $categories=calendarImportCategories($db);
    if($action==='import_review_list') {
        if(!empty($_SESSION['_rbac_worker']))throw new InvalidArgumentException('Review workspaces cannot be accessed during publishing approval.');
        $savedAt="CAST(JSON_UNQUOTE(JSON_EXTRACT(preview_json,'$.review.saved_at')) AS UNSIGNED)";
        $q=$db->prepare("SELECT token,filename,$savedAt AS saved_at,JSON_LENGTH(JSON_EXTRACT(preview_json,'$.review.rows')) AS row_count,CAST(JSON_EXTRACT(preview_json,'$.review.version') AS UNSIGNED) AS review_version FROM calendar_import_batches WHERE user_id=? AND state='preview' AND calendar_action IS NULL AND $savedAt>=? ORDER BY saved_at DESC LIMIT 100");
        $q->execute([$userId,time()-30*86400]);$reviews=[];
        while($batch=$q->fetch(PDO::FETCH_ASSOC)) {
            $reviews[]=['token'=>$batch['token'],'filename'=>$batch['filename'],'saved_at'=>(int)$batch['saved_at'],'review_version'=>(int)$batch['review_version'],'rows'=>(int)$batch['row_count']];
        }
        return ['success'=>true,'reviews'=>$reviews];
    }
    if($action==='import_manage_preview') {
        $operation=$_POST['calendar_action']??'';
        if(!in_array($operation,['archive','delete'],true))throw new InvalidArgumentException('Choose Archive or Delete.');
        if($operation==='delete'&&!hasPermission('delete_event')) {http_response_code(403);throw new InvalidArgumentException('You do not have permission to delete calendar events.');}
        $q=$db->prepare("SELECT * FROM calendar_import_series WHERE series_id=? AND status<>'deleted'");$q->execute([(int)($_POST['series_id']??0)]);$series=$q->fetch(PDO::FETCH_ASSOC);
        if(!$series)throw new InvalidArgumentException('Imported calendar not found.');
        if($operation==='archive'&&$series['status']==='archived')throw new InvalidArgumentException('This calendar is already archived.');
        $existing=calendarImportExisting($db,(int)$series['series_id']);
        $rows=[];
        foreach($existing as $event)$rows[]=['event_id'=>(int)$event['event_id'],'title'=>$event['title'],'start_date'=>$event['start_date'],'end_date'=>$event['end_date'],'location'=>$event['location']??'','category_id'=>$event['category_id'],'action'=>$operation,'signature'=>calendarEventSignature($event)];
        $token=bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO calendar_import_batches(token,series_id,user_id,base_revision,filename,preview_json,calendar_action) VALUES(?,?,?,?,?,?,?)')->execute([$token,$series['series_id'],$userId,$series['revision'],$series['name'],json_encode(['rows'=>$rows,'existing'=>$existing],JSON_THROW_ON_ERROR),$operation]);
        logActivity($userId,'IMPORT_PREVIEW','calendar_import_series',(int)$series['series_id'],'Reviewed calendar '.$operation.' request.');
        return ['success'=>true,'token'=>$token,'rows'=>$rows,'calendar_name'=>$series['name'],'calendar_action'=>$operation];
    }
    if($action==='import_preview') {
        $file=$_FILES['calendar_file']??[];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION))!=='docx')throw new InvalidArgumentException('Choose a Word .docx file up to 5 MB.');
        $year=filter_var($_POST['start_year']??'',FILTER_VALIDATE_INT);
        if(!$year||$year<1900||$year>2050)throw new InvalidArgumentException('Enter the calendar year (1900–2050).');
        $seriesId=(int)($_POST['series_id']??0);
        $series=null;
        if($seriesId) {
            $q=$db->prepare('SELECT * FROM calendar_import_series WHERE series_id=?');$q->execute([$seriesId]);$series=$q->fetch(PDO::FETCH_ASSOC);
            if(!$series||$series['status']!=='active')throw new InvalidArgumentException('Choose an active calendar for revised documents.');
            $year=(int)$series['start_year'];
        }
        $rows=calendarExtractXml(calendarDocxXml($file['tmp_name']),$year,$categories);
        try {
            $programDepartments=array_column($db->query('SELECT program_code,dept_id FROM programs')->fetchAll(PDO::FETCH_ASSOC),'dept_id','program_code');
        } catch(Throwable $e) {
            $programDepartments=[];
        }
        $programs=[];
        foreach(getAnnouncementProgramCategories() as $code=>$name)if($code!=='ALL')$programs[]=['code'=>$code,'name'=>$name,'dept_id'=>$programDepartments[$code]??null];
        if(eventDepartmentsReady()) {
            try {
                $deptList=$db->query('SELECT dept_id,dept_name,slug,department_type FROM departments')->fetchAll(PDO::FETCH_ASSOC);
            } catch(Throwable $e) {
                try {
                    $deptList=$db->query('SELECT dept_id,dept_name,slug FROM departments')->fetchAll(PDO::FETCH_ASSOC);
                } catch(Throwable $e2) {
                    $deptList=[];
                }
            }
            $rows=calendarImportDetectDepartments($rows,$deptList,$programs,eventAudienceGroupsReady());
        } else {
            $rows=calendarImportDetectDepartments($rows,[],$programs,eventAudienceGroupsReady());
        }
        if(!$series) {
            $name=trim((string)($_POST['calendar_name']??''));
            $nameLen=function_exists('mb_strlen')?mb_strlen($name,'UTF-8'):strlen($name);
            if($name===''||$nameLen>200)throw new InvalidArgumentException('Enter a calendar name up to 200 characters.');
            $db->prepare('INSERT INTO calendar_import_series(name,start_year,user_id) VALUES(?,?,?)')->execute([$name,$year,$userId]);
            $seriesId=(int)$db->lastInsertId();$series=['revision'=>0];
        }
        $existing=calendarImportExisting($db,$seriesId);
        $all=$db->query('SELECT event_id,title,start_date FROM events')->fetchAll(PDO::FETCH_ASSOC);
        $rows=calendarImportCompare($rows,$existing,$all);
        $token=bin2hex(random_bytes(32));
        $safeFilename=function_exists('mb_substr')?mb_substr(basename($file['name']),0,255,'UTF-8'):substr(basename($file['name']),0,255);
        $db->prepare('INSERT INTO calendar_import_batches(token,series_id,user_id,base_revision,filename,preview_json) VALUES(?,?,?,?,?,?)')->execute([$token,$seriesId,$userId,$series['revision'],$safeFilename,json_encode(['rows'=>$rows,'existing'=>$existing],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)]);
        logActivity($userId,'IMPORT_PREVIEW','events',null,'Prepared calendar import preview ('.count($rows).' rows).');
        return ['success'=>true,'token'=>$token,'rows'=>$rows,'existing'=>$existing,'categories'=>$categories,
            'departments'=>$deptList??[],
            'programs'=>$programs,'departments_ready'=>eventDepartmentsReady(),'audience_groups_ready'=>eventAudienceGroupsReady()];
    }
    $token=$_POST['import_token']??'';
    if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Invalid import preview.');
    $q=$db->prepare('SELECT *, TIMESTAMPDIFF(SECOND,created_at,NOW()) AS age_seconds FROM calendar_import_batches WHERE token=?');$q->execute([$token]);$batch=$q->fetch(PDO::FETCH_ASSOC);
    $worker=!empty($_SESSION['_rbac_worker']);
    if(!$batch||(int)$batch['user_id']!==($worker?rbacContentOwnerId():$userId))throw new InvalidArgumentException('Import preview not found for your account.');
    if(in_array($action,['import_review_save','import_review_resume'],true)) {
        if($worker||$batch['state']!=='preview'||!empty($batch['calendar_action']))throw new InvalidArgumentException('Only an active import preview can be saved or resumed.');
        $snapshot=json_decode($batch['preview_json'],true,64,JSON_THROW_ON_ERROR);
        if(calendarImportReviewExpired($batch,$snapshot))throw new InvalidArgumentException('This review expired. Extract the document again.');
        $version=(int)($snapshot['review']['version']??0);
        if($action==='import_review_resume') {
            if(!$version)throw new InvalidArgumentException('This preview has no saved review.');
            return ['success'=>true,'token'=>$token,'filename'=>$batch['filename'],'rows'=>$snapshot['review']['rows'],'view'=>$snapshot['review']['view'],'review_version'=>$version,'existing'=>$snapshot['existing'],'categories'=>$categories]+calendarImportReviewCatalog($db);
        }
        $raw=$_POST['review_workspace']??'';
        if(!is_string($raw)||strlen($raw)>20*1024*1024)throw new InvalidArgumentException('The review workspace is too large.');
        $workspace=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($workspace)||!is_array($workspace['rows']??null)||!is_array($workspace['view']??null))throw new InvalidArgumentException('Invalid review workspace.');
        if(($workspace['version']??null)!==$version)throw new InvalidArgumentException('This review was saved in another tab. Resume its latest saved copy before making changes.');
        $review=['rows'=>calendarImportReviewRows($workspace['rows'],$snapshot),'view'=>calendarImportReviewView($workspace['view']),'saved_at'=>time(),'version'=>$version+1];
        $snapshot['review']=$review;
        $q=$db->prepare("UPDATE calendar_import_batches SET preview_json=? WHERE token=? AND user_id=? AND state='preview' AND preview_json=?");
        $q->execute([json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),$token,$userId,$batch['preview_json']]);
        if($q->rowCount()!==1)throw new InvalidArgumentException('This review changed in another request. Resume its latest saved copy.');
        return ['success'=>true,'review_version'=>$review['version'],'saved_at'=>$review['saved_at']];
    }
    if($action==='import_cancel') {
        $snapshot=json_decode($batch['preview_json'],true,64,JSON_THROW_ON_ERROR);
        if(!empty($snapshot['review'])) {
            if(($_POST['discard_saved']??'')!=='1')return ['success'=>true];
            if($worker || (string)($_POST['review_version']??'')!==(string)$snapshot['review']['version'])throw new InvalidArgumentException('This saved review changed. Reload the saved review list before discarding.');
        }
        $q=$db->prepare("UPDATE calendar_import_batches SET state='cancelled', preview_json='[]' WHERE token=? AND state='preview' AND preview_json=?");
        $q->execute([$token,$batch['preview_json']]);
        if($q->rowCount()!==1)throw new InvalidArgumentException('This preview changed. Refresh before discarding.');
        logActivity($userId,'IMPORT_CANCEL','events',null,'Cancelled calendar preview.');return ['success'=>true];
    }
    if($action!=='import_confirm')throw new InvalidArgumentException('Unknown calendar import action.');
    if($batch['state']==='applied')return ['success'=>true,'message'=>'This import has already been applied.'];
    if($worker) {
        if($batch['state']!=='submitted')throw new InvalidArgumentException('This import is not awaiting approval.');
        calendarImportApply($db,$batch,json_decode($batch['approved_json'],true,64,JSON_THROW_ON_ERROR),'published',$userId);
        return ['success'=>true,'message'=>!empty($batch['calendar_action'])?'Calendar '.$batch['calendar_action'].' request approved and applied.':'Calendar import approved and published.'];
    }
    if($batch['state']!=='preview')throw new InvalidArgumentException('This preview has already been submitted. Upload again to make another revision.');
    $snapshot=json_decode($batch['preview_json'],true,64,JSON_THROW_ON_ERROR);
    if(calendarImportReviewExpired($batch,$snapshot))throw new InvalidArgumentException('This preview expired. Upload again to compare current events.');
    if(!empty($snapshot['review']) && (string)($_POST['review_version']??'')!==(string)$snapshot['review']['version'])throw new InvalidArgumentException('This review changed in another tab. Resume its latest saved copy before confirming.');
    $preview=$snapshot['rows'];
    $raw=(string)($_POST['import_rows']??'[]');
    if(strlen($raw)>20*1024*1024)throw new InvalidArgumentException('The edited calendar is too large. Import fewer activities at a time.');
    $edits=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($edits))throw new InvalidArgumentException('Invalid preview selection.');
    $existing=calendarImportExisting($db,(int)$batch['series_id']);
    // Preserve the preview snapshot: concurrent edits must not become the new baseline.
    $existingById=array_column($existing,null,'event_id');
    foreach($snapshot['existing'] as $event) if(!isset($existingById[$event['event_id']])||calendarEventSignature($event)!==calendarEventSignature($existingById[$event['event_id']]))throw new InvalidArgumentException('An imported event changed since preview. Upload again before confirming.');
    if(!empty($batch['calendar_action'])) {
        if(($_POST['reviewed_all']??'')!=='1')throw new InvalidArgumentException('Review the affected events and confirm this calendar action.');
        if($batch['calendar_action']==='delete'&&!hasPermission('delete_event')) {http_response_code(403);throw new InvalidArgumentException('You do not have permission to delete calendar events.');}
        $rows=$preview;
    } else $rows=calendarImportValidate($edits,$preview,$existing,$categories);
    $status=hasPermission('publish_event')&&($_POST['import_status']??'')==='published'?'published':'draft';
    $q=$db->prepare("UPDATE calendar_import_batches SET approved_json=?, state='ready' WHERE token=? AND state='preview' AND preview_json=?");
    $q->execute([json_encode($rows,JSON_THROW_ON_ERROR),$token,$batch['preview_json']]);
    if($q->rowCount()!==1)throw new InvalidArgumentException('This preview is already being confirmed.');
    try {
        if(!hasPermission('publish_event')) {
            $id=rbacSaveSubmission('request','/admin/events.php','Calendar '.($batch['calendar_action']?:'import').': '.$batch['filename'],['post'=>['ajax_action'=>'import_confirm','import_token'=>$token],'files'=>[]]);
            $db->prepare("UPDATE calendar_import_batches SET state='submitted' WHERE token=? AND state='ready'")->execute([$token]);
            return ['success'=>true,'submitted'=>true,'submission_id'=>$id,'message'=>'Calendar request submitted for Super Admin approval. Published content is unchanged.'];
        }
        calendarImportApply($db,$batch,$rows,$status,$userId);
        return ['success'=>true,'message'=>!empty($batch['calendar_action'])?($batch['calendar_action']==='delete'?'Imported calendar and its events deleted.':'Imported calendar archived. Its events are hidden from the website.') : ($status==='published'?'Reviewed calendar events published.':'Calendar events saved as drafts. Publish them through Events when ready.')];
    } catch(Throwable $e) {
        $db->prepare("UPDATE calendar_import_batches SET state='preview' WHERE token=? AND state='ready'")->execute([$token]);throw $e;
    }
}
