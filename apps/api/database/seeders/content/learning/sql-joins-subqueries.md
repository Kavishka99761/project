# SQL Joins and Subqueries

Relational databases store related data in separate tables, so most useful queries must combine rows from more than one table. SQL provides joins to combine tables side by side and subqueries to use the result of one query inside another. Choosing the right technique makes queries correct, readable and fast.

## Inner joins

An inner join returns only the rows that have matching values in both tables. The join condition is written in the ON clause, for example ON Orders.CustomerID = Customers.CustomerID. Rows without a match on either side are excluded from the result. Inner joins are the most common join because most questions concern records that are actually related, such as orders together with the customers who placed them.

## Outer joins

A left outer join returns every row from the left table and the matching rows from the right table. When there is no match, the columns from the right table contain NULL. A left join answers questions such as "list all customers and their orders, including customers who never ordered". A right outer join does the same with the tables reversed, and a full outer join keeps unmatched rows from both sides. Filtering an outer join in the WHERE clause can accidentally turn it back into an inner join, so conditions on the optional table usually belong in the ON clause.

## Self joins and cross joins

A self join joins a table to itself using two different aliases. It is used for hierarchical data, for example to list each employee next to the name of their manager when both are stored in the Employee table. A cross join returns the Cartesian product of two tables, pairing every row of the first table with every row of the second. Cross joins are rarely needed, but they are useful for generating combinations such as every student paired with every exam session.

## Subqueries

A subquery is a SELECT statement nested inside another statement. A scalar subquery returns a single value and can be used wherever a value is expected, for example to compare each salary with the average salary. A subquery used with IN returns a list of values, such as the IDs of students enrolled in a given module. Correlated subqueries refer to columns of the outer query and are evaluated once for each outer row, which makes them expressive but potentially slow on large tables.

## EXISTS versus IN

The EXISTS operator tests whether a subquery returns at least one row. EXISTS stops as soon as it finds a match, which often makes it faster than IN for large correlated checks. NOT IN behaves unexpectedly when the subquery returns a NULL value, because every comparison with NULL is unknown. For this reason NOT EXISTS is the safer choice for anti-joins such as "customers with no orders".

## Common table expressions

A common table expression (CTE) is a named temporary result defined with the WITH keyword. CTEs make complex queries easier to read by breaking them into named steps. A recursive CTE references itself and is the standard way to traverse hierarchies such as organisation charts or category trees in SQL Server.

## Performance tips

Joins perform best when the joined columns are indexed, which is why foreign key columns usually have an index. Select only the columns you need instead of using SELECT *. Inspect the execution plan in SQL Server Management Studio to see whether the optimizer uses index seeks or expensive table scans. Rewriting a correlated subquery as a join or a CTE frequently produces a much faster plan.
