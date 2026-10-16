-- Fixed Basic Education departments used by the content editor.
-- Safe to run again: existing rows, logos, and Head of Office details are untouched.
INSERT INTO departments (dept_name, slug, department_type)
VALUES ('CASA and Grade School Montessori', 'casa-gradeschool', 'basic-education')
ON DUPLICATE KEY UPDATE dept_id = dept_id;

INSERT INTO departments (dept_name, slug, department_type)
VALUES ('High School Montessori', 'hs-monte', 'basic-education')
ON DUPLICATE KEY UPDATE dept_id = dept_id;

INSERT INTO departments (dept_name, slug, department_type)
VALUES ('Junior High School Regular', 'jhs-regular', 'basic-education')
ON DUPLICATE KEY UPDATE dept_id = dept_id;

INSERT INTO departments (dept_name, slug, department_type)
VALUES ('Senior High School Regular', 'shs-regular', 'basic-education')
ON DUPLICATE KEY UPDATE dept_id = dept_id;
