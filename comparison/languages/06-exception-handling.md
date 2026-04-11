# Exception Handling — Java vs PHP

## Giris

Exception handling (istisna idareetmesi) proqramlashdirmada qachilinmaz xetalarin idarə olunmasi ucun istifade edilir. Java ve PHP her ikisi try-catch mexanizminden istifade etse de, onlarin yanasmasi koklunden ferqlenir. Java **checked** ve **unchecked** exception sistemi ile daha sert qaydalara malikdir, PHP ise daha serbest bir model tetbiq edir.

Bu meqalede her iki dilin exception sistemini muqayise edeceyik, real kod numuneleri ile ferqleri aydinlasdiracagiq.

---

## Java-da istifadesi

### Try-Catch-Finally

Java-da exception handling ucun `try`, `catch`, `finally` bloklari istifade olunur:

```java
public class FileReaderExample {
    public static void main(String[] args) {
        FileInputStream fis = null;
        try {
            fis = new FileInputStream("data.txt");
            int data = fis.read();
            System.out.println("Data: " + data);
        } catch (FileNotFoundException e) {
            System.err.println("Fayl tapilmadi: " + e.getMessage());
        } catch (IOException e) {
            System.err.println("Oxuma xetasi: " + e.getMessage());
        } finally {
            // finally bloku HER ZAMAN isleyir — xeta olsa da, olmasa da
            try {
                if (fis != null) fis.close();
            } catch (IOException e) {
                System.err.println("Fayl baglanarkən xeta: " + e.getMessage());
            }
        }
    }
}
```

Java 7+ ile **try-with-resources** daha temiz yanasmani teqdim edir:

```java
public class FileReaderModern {
    public static void main(String[] args) {
        // AutoCloseable interfeysini implement eden resurslar avtomatik baglanir
        try (FileInputStream fis = new FileInputStream("data.txt");
             BufferedReader reader = new BufferedReader(new InputStreamReader(fis))) {

            String line;
            while ((line = reader.readLine()) != null) {
                System.out.println(line);
            }
        } catch (FileNotFoundException e) {
            System.err.println("Fayl tapilmadi: " + e.getMessage());
        } catch (IOException e) {
            System.err.println("Oxuma xetasi: " + e.getMessage());
        }
        // finally yazmaga ehtiyac yoxdur — resurslar avtomatik baglanir
    }
}
```

### Checked vs Unchecked Exceptions

Bu, Java-nin en muhum ve en cox mubahhise doghuran xususiyyetlerindendir.

**Checked Exceptions** — kompilyator seviyyesinde yoxlanilir. Metod ya onu tutmali (`catch`), ya da yuxari otürmeli (`throws`):

```java
import java.io.*;
import java.sql.*;

public class CheckedExceptionExample {

    // IOException checked exception-dir — throws ile bildirmek MECBURIDIR
    public String readFile(String path) throws IOException {
        BufferedReader reader = new BufferedReader(new FileReader(path));
        return reader.readLine();
    }

    // SQLException de checked exception-dir
    public void connectDatabase() throws SQLException {
        Connection conn = DriverManager.getConnection("jdbc:mysql://localhost/db");
        // ...
    }

    public void process() {
        try {
            String content = readFile("config.txt");
            connectDatabase();
        } catch (IOException e) {
            // Fayl xetalarini idare et
            System.err.println("Fayl xetasi: " + e.getMessage());
        } catch (SQLException e) {
            // Database xetalarini idare et
            System.err.println("Database xetasi: " + e.getMessage());
        }
    }
}
```

**Unchecked Exceptions** — `RuntimeException`-dan toreyir, kompilyator onlari yoxlamir:

```java
public class UncheckedExceptionExample {

    public int divide(int a, int b) {
        // ArithmeticException — unchecked, throws yazmaga ehtiyac yoxdur
        return a / b;
    }

    public String getFirstChar(String text) {
        // NullPointerException — unchecked
        // StringIndexOutOfBoundsException — unchecked
        return String.valueOf(text.charAt(0));
    }

    public static void main(String[] args) {
        UncheckedExceptionExample ex = new UncheckedExceptionExample();

        // Bu xetalar runtime-da bash verir, kompilyator xeberdarlik etmir
        try {
            ex.divide(10, 0);
        } catch (ArithmeticException e) {
            System.err.println("Sifira bolme: " + e.getMessage());
        }

        try {
            ex.getFirstChar(null);
        } catch (NullPointerException e) {
            System.err.println("Null reference: " + e.getMessage());
        }
    }
}
```

### Java Exception Hierarchy (iyerarxiyasi)

```
Throwable
├── Error (ciddi sistem xetalari — tutulmamalidir)
│   ├── OutOfMemoryError
│   ├── StackOverflowError
│   └── VirtualMachineError
│
└── Exception
    ├── Checked Exceptions (kompilyator yoxlayir)
    │   ├── IOException
    │   ├── SQLException
    │   ├── ClassNotFoundException
    │   └── ParseException
    │
    └── RuntimeException (Unchecked — kompilyator yoxlamir)
        ├── NullPointerException
        ├── IllegalArgumentException
        ├── ArrayIndexOutOfBoundsException
        ├── ArithmeticException
        └── ClassCastException
```

### Throws keyword

`throws` metod imzasinda istifade olunaraq, bu metodun hansI checked exception-lari ata bileceyini bildirir:

```java
public class OrderService {

    // Bir nece exception ata biler
    public Order createOrder(OrderRequest request)
            throws ValidationException, PaymentException, InventoryException {

        if (request == null) {
            throw new ValidationException("Sifaris melumatlari bosh ola bilmez");
        }

        if (!paymentService.charge(request.getAmount())) {
            throw new PaymentException("Odenis ugursuz oldu");
        }

        if (!inventory.isAvailable(request.getProductId())) {
            throw new InventoryException("Mehsul movcud deyil");
        }

        return new Order(request);
    }
}
```

### Custom Exceptions (Xususi istisna sinifleri)

```java
// Checked exception — Exception-dan toreyir
public class InsufficientBalanceException extends Exception {
    private final double currentBalance;
    private final double requestedAmount;

    public InsufficientBalanceException(double currentBalance, double requestedAmount) {
        super(String.format(
            "Balans kifayet etmir. Movcud: %.2f, Teleb olunan: %.2f",
            currentBalance, requestedAmount
        ));
        this.currentBalance = currentBalance;
        this.requestedAmount = requestedAmount;
    }

    public double getCurrentBalance() { return currentBalance; }
    public double getRequestedAmount() { return requestedAmount; }
    public double getDeficit() { return requestedAmount - currentBalance; }
}

// Unchecked exception — RuntimeException-dan toreyir
public class InvalidConfigurationException extends RuntimeException {
    public InvalidConfigurationException(String key) {
        super("Yanlish konfiqurasiya acari: " + key);
    }
}

// Istifadesi
public class BankAccount {
    private double balance;

    public void withdraw(double amount) throws InsufficientBalanceException {
        if (amount > balance) {
            throw new InsufficientBalanceException(balance, amount);
        }
        balance -= amount;
    }
}
```

### Multi-catch (Java 7+)

```java
try {
    // ...
} catch (IOException | SQLException | ParseException e) {
    // Bir nece exception tipi eyni blokda tutulur
    logger.error("Xeta bash verdi: " + e.getMessage());
}
```

---

## PHP-de istifadesi

### Try-Catch-Finally

PHP-de exception handling Java-ya oxshayir, lakin checked exception anlayishi yoxdur:

```php
<?php

function readFile(string $path): string
{
    try {
        if (!file_exists($path)) {
            throw new RuntimeException("Fayl tapilmadi: $path");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Fayl oxuna bilmedi: $path");
        }

        return $content;

    } catch (RuntimeException $e) {
        echo "Xeta: " . $e->getMessage() . PHP_EOL;
        return '';

    } finally {
        // Java-da oldugu kimi, her zaman isleyir
        echo "Emeliyyat tamamlandi." . PHP_EOL;
    }
}
```

### PHP Exception Hierarchy (iyerarxiyasi)

```
Throwable (interfeys)
├── Error (ciddi xetalar — PHP 7+)
│   ├── TypeError
│   ├── ValueError (PHP 8+)
│   ├── ArithmeticError
│   │   └── DivisionByZeroError
│   ├── ParseError
│   └── ArgumentCountError
│
└── Exception
    ├── RuntimeException
    │   ├── OverflowException
    │   ├── UnderflowException
    │   ├── OutOfBoundsException
    │   └── UnexpectedValueException
    ├── LogicException
    │   ├── InvalidArgumentException
    │   ├── BadMethodCallException
    │   ├── DomainException
    │   └── LengthException
    └── PDOException
```

PHP 7-den evvel yalniz `Exception` var idi. PHP 7 ile `Throwable` interfeysi ve `Error` sinfi elave olundu:

```php
<?php

// PHP 7+ ile hem Error, hem Exception tutula biler
try {
    $result = someUndefinedFunction();
} catch (\Error $e) {
    echo "Error: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage();
}

// ve ya Throwable ile hamisini tutmaq mumkundur
try {
    $result = someUndefinedFunction();
} catch (\Throwable $e) {
    echo "Xeta: " . $e->getMessage();
}
```

### PHP 8+ Union Catch ve Non-capturing Catch

```php
<?php

// PHP 8.0: Union catch — Java-nin multi-catch-ine oxshayir
try {
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $db->query($sql);
} catch (JsonException | PDOException $e) {
    log_error($e->getMessage());
}

// PHP 8.0: Non-capturing catch — deyishen lazim deyilse
try {
    $this->saveToCache($data);
} catch (CacheException) {
    // $e deyisheni lazim deyil, sadece kecib gedirik
    // Java-da bu mumkun deyil — deyishen adi MECBURIDIR
}
```

### Custom Exceptions (Xususi istisna sinifleri)

```php
<?php

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        private readonly float $currentBalance,
        private readonly float $requestedAmount,
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Balans kifayet etmir. Movcud: %.2f, Teleb olunan: %.2f',
            $currentBalance,
            $requestedAmount
        );

        parent::__construct($message, 0, $previous);
    }

    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }

    public function getRequestedAmount(): float
    {
        return $this->requestedAmount;
    }

    public function getDeficit(): float
    {
        return $this->requestedAmount - $this->currentBalance;
    }
}

// Istifadesi
class BankAccount
{
    private float $balance;

    public function withdraw(float $amount): void
    {
        if ($amount > $this->balance) {
            throw new InsufficientBalanceException($this->balance, $amount);
        }
        $this->balance -= $amount;
    }
}

// Catch
try {
    $account->withdraw(500.00);
} catch (InsufficientBalanceException $e) {
    echo $e->getMessage();
    echo "Catishmayan meblegh: " . $e->getDeficit();
}
```

### Exception Chaining (zencirli istisna)

Her iki dilde de exception-lari zencirleme mumkundur:

```php
<?php

// PHP
try {
    $db->connect();
} catch (PDOException $e) {
    // Orijinal xetani saxlayaraq yeni exception atiriq
    throw new DatabaseConnectionException(
        'Database-e qosulmaq mumkun olmadi',
        previous: $e  // PHP 8 named argument
    );
}
```

```java
// Java
try {
    DriverManager.getConnection(url);
} catch (SQLException e) {
    // Orijinal xetani saxlayaraq yeni exception atiriq
    throw new DatabaseConnectionException("Database-e qosulmaq mumkun olmadi", e);
}
```

---

## Esas ferqler

| Xususiyyet | Java | PHP |
|---|---|---|
| **Checked exceptions** | Var — kompilyator yoxlayir | Yoxdur |
| **`throws` keyword** | Var — metod imzasinda mecburi | Yoxdur (PHPDoc ile `@throws` yazila biler, amma mecburi deyil) |
| **Exception iyerarxiyasi** | `Throwable → Error/Exception` | `Throwable → Error/Exception` (PHP 7+) |
| **Multi-catch** | `catch (A \| B e)` (Java 7+) | `catch (A \| B $e)` (PHP 8+) |
| **Non-capturing catch** | Yoxdur | `catch (Exception)` (PHP 8+) |
| **Try-with-resources** | Var (Java 7+) | Yoxdur (destructor ve ya `finally` istifade olunur) |
| **Custom exception** | Class extend edir | Class extend edir |
| **`finally` bloku** | Var | Var |

---

## Niye bele ferqler var?

### Java niye checked exceptions istifade edir?

Java-nin yaradicilarinin felsefesi bu idi: **eger bir metod xeta ata bilirse, caghiran kod bu xetani idare etmeye MECBUR olmalidir**. Bu, proqramcini xetalari gormezden gelmeye qoymur. Kompilyator seviyyesinde yoxlama, boyuk enterprise proqramlarda xetalarin "unutulmamasi" ucun nezerde tutulmusdur.

Lakin bu yanasdirma illər keçdikcə tenqid olundu:
- Kod çox uzun ve "boilerplate" dolu olur
- Bezi proqramcilar checked exception-lari bosh catch bloklari ile "yuduruler"
- Modern Java frameworkleri (Spring meselen) demek olar ki yalniz unchecked exception istifade edir

### PHP niye checked exceptions istifade etmir?

PHP dinamik tipli ve interpretasiya olunan bir dildir. Kompilyasiya merhəlesi olmadighi ucun checked exception sistemi texniki cehetden mumkun deyil. PHP-nin felsefesi daha praktik ve sadedir: **proqramci ozu bilir hansi xetalari idare etmelidir**.

PHP 7 ile `Throwable` interfeysi ve `Error` sinfi elave olunaraq, exception sistemi daha strukturlu hala getirildi. PHP 8 ise union catch ve non-capturing catch ile Java-ya yaxinlashdi, amma mexburi yoxlama olmadan.

### Dizayn felsefesi

- **Java**: "Kompilyator seni qoruyur. Butun mumkun xetalari idare et."
- **PHP**: "Proqramci oz mesuliyyetini ozu dashiyir. Lazim olan yerde exception tut."

Her iki yanasdirmanin da ustuunlukleri ve catishmazliqlari var. Java-nin yanasdirmasi daha tehlukesiz, amma daha chetindir. PHP-nin yanasdirmasi daha serbest, amma xetalarin gozden qaçma riski daha yuksekdir.
