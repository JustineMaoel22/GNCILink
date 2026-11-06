<?php
/** Public save entry points preserve old payloads while coordinating shared identities. */
function saveAdministrationPerson(int $personId,string $sectionKey,string $name,string $role,?array $photoFile,bool $removePhoto): void {
    personnelWrite('person',$personId,$name,static fn($resolved)=>saveAdministrationPersonRecord($personId,$sectionKey,$resolved,$role,$photoFile,$removePhoto));
}
function saveAdministrationOffice(int $officeId,string $sectionKey,string $officeName,string $headName,string $headRole,string $phone,string $email,string $location,string $hours,string $services,?array $photoFile,bool $removePhoto): void {
    personnelWrite('office',$officeId,$headName,static fn($resolved)=>saveAdministrationOfficeRecord($officeId,$sectionKey,$officeName,$resolved,$headRole,$phone,$email,$location,$hours,$services,$photoFile,$removePhoto));
}
function saveDepartmentDean(int $deptId,string $name,string $title,string $bio,string $phone,string $email,string $office,string $hours,?array $photoFile,bool $removePhoto): array {
    return personnelWrite('dean',$deptId,$name,static fn($resolved)=>saveDepartmentDeanRecord($deptId,$resolved,$title,$bio,$phone,$email,$office,$hours,$photoFile,$removePhoto));
}
function saveHomepagePresident(string $name,string $title,string $org,string $eyebrow,string $heading,string $greeting,string $message,?array $photoFile,bool $removePhoto): array {
    return personnelWrite('president',1,$name,static fn($resolved)=>saveHomepagePresidentRecord($resolved,$title,$org,$eyebrow,$heading,$greeting,$message,$photoFile,$removePhoto));
}
