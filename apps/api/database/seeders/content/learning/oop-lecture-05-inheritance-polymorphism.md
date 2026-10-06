# OOP Lecture 05: Inheritance and Polymorphism

Inheritance and polymorphism are two of the four pillars of object-oriented programming, together with encapsulation and abstraction. They allow programmers to build families of related classes that share behaviour while still allowing each class to specialise that behaviour. Used well, they reduce duplicated code and make systems easier to extend.

## Inheritance

Inheritance is a mechanism in which a new class acquires the attributes and methods of an existing class. The existing class is called the superclass or base class, and the new class is called the subclass or derived class. In Java a subclass is declared with the extends keyword, for example class Car extends Vehicle. The subclass inherits the public and protected members of the superclass and can add new fields and methods of its own. Inheritance models an "is-a" relationship: a car is a vehicle, and a savings account is a bank account.

## Method overriding

Method overriding happens when a subclass provides its own implementation of a method that is already defined in its superclass. The overriding method must have the same name, parameter list and compatible return type. The @Override annotation asks the compiler to check that a method really overrides a superclass method, which prevents subtle spelling mistakes. A subclass can still call the original version with the super keyword, for example super.describe().

## Constructors and the super keyword

Constructors are not inherited, but every subclass constructor must call a superclass constructor as its first statement. If the programmer does not write the call, the compiler inserts super() automatically. When the superclass has no default constructor, the subclass must call an appropriate constructor explicitly, such as super(registrationNumber, owner).

## Polymorphism

Polymorphism is the ability of a single interface to represent objects of different types. With subtype polymorphism, a variable of a superclass type can refer to an object of any of its subclasses. When a method is called through that variable, the version that runs is chosen at run time from the actual type of the object. This process is called dynamic binding or late binding. For example, a List of Shape objects can hold circles, rectangles and triangles, and calling area() on each element runs the correct formula for each shape.

## Overloading versus overriding

Method overloading means defining several methods with the same name but different parameter lists in the same class. Overloading is resolved at compile time, so it is called static polymorphism. Overriding is resolved at run time and is the basis of dynamic polymorphism. Students often confuse the two: overloading changes the parameters, while overriding keeps the signature and changes the behaviour.

## Abstract classes and interfaces

An abstract class is a class that cannot be instantiated and may contain abstract methods without a body. Subclasses must implement every abstract method unless they are abstract themselves. An interface is a contract that lists methods a class promises to provide. A class can implement many interfaces but can extend only one class in Java, so interfaces are the main tool for combining capabilities such as Comparable and Serializable.

## Design guidance

Favour composition over inheritance when the relationship is "has-a" rather than "is-a". Deep inheritance hierarchies are fragile because a change in a base class can break many subclasses. The Liskov Substitution Principle states that objects of a subclass must be usable wherever the superclass is expected without changing the correctness of the program. Following this principle keeps polymorphic code predictable and easy to test.
