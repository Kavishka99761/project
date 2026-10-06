# Database Normalization

Normalization is the process of organising data in a relational database to reduce redundancy and improve data integrity. It was first proposed by Edgar F. Codd as part of the relational model. A poorly designed table often stores the same fact in several rows, which wastes storage and causes update anomalies. Normalization works by splitting large tables into smaller, well-structured tables and connecting them through keys.

## Why redundancy is a problem

An update anomaly occurs when changing one fact requires changing many rows. If a lecturer's office number is stored in every row of a Course table, moving office means updating dozens of rows, and missing one leaves the database inconsistent. An insertion anomaly happens when we cannot record a fact without also recording an unrelated fact, for example when a new lecturer cannot be stored until they are assigned a course. A deletion anomaly happens when deleting a row accidentally removes information we still need. Normalization removes these anomalies by splitting tables according to functional dependencies.

## Keys and functional dependencies

A candidate key is a minimal set of attributes that uniquely identifies every row of a table. The primary key is the candidate key chosen by the designer, and a foreign key is an attribute that refers to the primary key of another table. A functional dependency is a relationship in which the value of one attribute determines the value of another attribute. For example, in a Student table the student ID determines the student name, which is written StudentID → Name. Functional dependencies are the foundation of every normal form, and identifying them correctly is the most important step in database design.

## First Normal Form (1NF)

A table is in first normal form when every column contains atomic values and there are no repeating groups. A column that stores a comma-separated list of phone numbers violates 1NF. Repeating groups such as Phone1, Phone2 and Phone3 must be moved into a separate table with one row per phone number. After this step every cell holds exactly one value.

## Second Normal Form (2NF)

A table is in second normal form when it is in first normal form and every non-key attribute is fully functionally dependent on the whole primary key. Partial dependencies appear only when a table has a composite primary key. Consider an Enrolment table with the key (StudentID, CourseID) that also stores CourseTitle. CourseTitle depends only on CourseID, so it is a partial dependency. To reach 2NF we move partially dependent attributes into a new table together with the part of the key they depend on.

## Third Normal Form (3NF)

A table is in third normal form when it is in second normal form and no non-key attribute is transitively dependent on the primary key. A transitive dependency exists when A determines B and B determines C. In an Employee table where EmployeeID determines DepartmentID and DepartmentID determines DepartmentName, the department name should move to a Department table. Most business databases are designed to third normal form because it balances integrity and performance.

## Boyce-Codd Normal Form (BCNF)

BCNF is a stricter version of third normal form in which every determinant must be a candidate key. In practice, BCNF violations are rare and usually involve overlapping candidate keys. A table can be in 3NF but not in BCNF when a non-key attribute determines part of a composite key.

## Denormalization

Denormalization is sometimes applied deliberately to improve read performance in reporting systems and data warehouses. It reintroduces controlled redundancy, so the design must include rules, triggers or batch jobs that keep the duplicated data consistent. A good database designer normalizes first and denormalizes only when measurements show a real performance problem.

## Summary of the process

To normalize a schema, list every attribute, identify the functional dependencies and candidate keys, then remove repeating groups (1NF), partial dependencies (2NF) and transitive dependencies (3NF). Check the result for BCNF and confirm that joining the new tables reproduces the original data without spurious rows, which is known as a lossless-join decomposition.
