# Encapsulation and Abstraction

These are my notes from the OOP tutorial on the first two pillars of object-oriented design.

## Encapsulation

Encapsulation is the practice of bundling data and the methods that operate on that data inside a single class while hiding the internal details from the outside world. Fields are declared private and are accessed through public getter and setter methods. This lets the class validate every change, for example rejecting a negative balance in a BankAccount class. Encapsulation protects the invariants of an object, which are the rules that must always be true about its state.

Access modifiers control visibility. Private members are visible only inside the class, protected members are visible to subclasses and the same package, and public members are visible everywhere. A useful rule is to make everything as private as possible and open it up only when there is a clear need.

Immutable objects take encapsulation further. An immutable class has only final fields, sets them in the constructor and provides no setters. String and LocalDate in Java are immutable, which makes them safe to share between threads.

## Abstraction

Abstraction is the process of exposing only the essential features of an object while hiding the unnecessary implementation details. When we call list.sort() we do not need to know which sorting algorithm runs underneath. Abstraction reduces complexity because users of a class depend on what it does rather than on how it does it.

In Java, abstraction is achieved with abstract classes and interfaces. An interface such as PaymentMethod can declare a pay(amount) method, and classes such as CardPayment and WalletPayment provide the details. The rest of the program works with the PaymentMethod abstraction, so a new payment type can be added without changing existing code.

## How they work together

Encapsulation hides the data, while abstraction hides the complexity of the behaviour. Together they support loose coupling, which means classes depend on each other as little as possible. Loose coupling makes code easier to test, because a class can be tested with a simple fake implementation of the interfaces it depends on.

## Exam tips

Be ready to explain the difference between encapsulation and abstraction with an example. Show how private fields with validated setters enforce invariants. Draw a small UML class diagram with an interface and two implementing classes, and explain why the client code depends only on the interface.
