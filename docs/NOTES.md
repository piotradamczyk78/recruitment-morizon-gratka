# Notatki

## Spis zadan

- [Zadanie 1 - Poprawa jakosci kodu](#notatki---zadanie-1-poprawa-jakosci-kodu) | [Tablica projektu](https://github.com/users/piotradamczyk78/projects/9)
- [Zadanie 2 - Import zdjec z PhoenixApi](#notatki---zadanie-2-import-zdjec-z-phoenixapi) | [Tablica projektu](https://github.com/users/piotradamczyk78/projects/10)
- [Zadanie 3 - Filtrowanie zdjec na stronie glownej](#notatki---zadanie-3-filtrowanie-zdjec-na-stronie-glownej) | [Tablica projektu](https://github.com/users/piotradamczyk78/projects/11)
- [Zadanie 4 - Rate-limiting w PhoenixApi (OTP)](#notatki---zadanie-4-rate-limiting-w-phoenixapi-otp) | [Tablica projektu](https://github.com/users/piotradamczyk78/projects/12)

---

# Notatki - Zadanie 1: Poprawa jakosci kodu

[Tablica projektu](https://github.com/users/piotradamczyk78/projects/9)

## Podejscie do pracy

Zaczalem od dokladnego code review oryginalnego kodu. Zidentyfikowalem 24 problemy,
pogrupowalem je wedlug wagi (Critical / Important / Architecture) i opisalem
w komentarzach inline w PR #2. Kazdy problem dostal osobne issue na GitHub Project board
ze statusami Todo / In Progress / Done.

Kazdy fix to osobny branch (`fix/*`), osobny PR do `develop`, atomic commit
z opisem co, jak i dlaczego. Po kazdym ulepszeniu wszystkie testy musza przejsc -
zero regresji. Calosc udokumentowana w historii gitowej i na tablicy projektu.

## Wprowadzone zmiany

### Critical (bezpieczenstwo)

- **SQL Injection w AuthController** (#3) - surowe zapytania ze sklejanymi stringami
  zamienione na prepared statements z parametrami
- **Credentials w URL** (#4) - token i username przesylane w URL (GET) przeniesione
  do POST body
- **Brak weryfikacji token-user** (#5) - mozna bylo zalogowac sie tokenem innego
  uzytkownika. Dodano JOIN walidujacy przynaleznosc tokenu
- **Race condition na like_counter** (#6) - `$photo->setLikeCounter($counter + 1)`
  zamienione na atomowy `UPDATE ... SET like_counter = like_counter + 1`
- **Brak UNIQUE INDEX na likes** (#7) - mozliwosc wielokrotnego polubienia tego samego
  zdjecia. Dodano migracje z UNIQUE INDEX na (user_id, photo_id)

### Important (poprawnosc i wydajnosc)

- **Like przez GET bez CSRF** (#8) - zmiana na POST + walidacja CSRF token per-photo
- **Podwojny flush bez atomowosci** (#9) - `unlikePhoto()` owiniety w `wrapInTransaction()`
- **N+1 query w galerii** (#10) - petla odpytywan zamieniona na batch
  `getUserLikedPhotoIds()` - jedno zapytanie na wszystkie zdjecia
- **Brak null-check na user** (#11) - stale session po usunieciu usera prowadzilo
  do bledow. Dodano walidacje i czyszczenie sesji
- **Stateful repozytorium** (#12) - `setUser()` na singletonie. User przeniesiony
  jako parametr do kazdej metody - repozytorium bezstanowe
- **Bledny docblock** (#13) - `@return JsonResponse` na metodzie zwracajacej `Response`,
  zduplikowany `@Route` annotation zamieniony na atrybut PHP 8
- **Catch-all Throwable** (#14) - `catch(\Throwable)` traciacy typ i stack trace
  wyjatku. Usunieto - wyjatki propaguja sie naturalnie
- **Nieefektywne getArrayResult()** (#15) - pobieranie wszystkich rekordow zeby
  sprawdzic count > 0. Zamienione na `setMaxResults(1)` + `getOneOrNullResult()`
- **Nullable setUser() vs non-nullable property** (#16) - sygnatura `setUser(?User)`
  przy `private User $user` i `JoinColumn(nullable: false)`. Poprawiono spojnosc typowania,
  naprawiono `removePhoto()` ktore wywolywalo `setUser(null)`

### Architecture (struktura i wzorce)

- **Dependency Injection** (#17) - kontrolery tworzace repozytoria przez `new` zamienione
  na wstrzykiwanie przez konstruktor z interfejsem
- **DBAL -> ORM w AuthController** (#18) - surowe zapytania SQL zastapione
  `AuthTokenRepository` z Doctrine QueryBuilder
- **Toggle like/unlike w serwisie** (#19, #20) - logika biznesowa przeniesiona
  z kontrolera do `LikeService::toggleLike()`. Spojna abstrakcja
- **Interface design** (#21) - `updatePhotoCounter()` lamalo SRP (aktualizowalo tabele
  `photos` z `LikeRepository`). Logika countera przeniesiona do `createLike()`
  w transakcji

## Testy

Napisalem dwa rodzaje testow:

**Testy jednostkowe** (PHPUnit + mocki):
- Entity: Photo (12 testow), User (7), AuthToken (6)
- LikeService (3 testy) - toggleLike, propagacja wyjatkow
- LikeRepository (2 testy) - weryfikacja bezstanowosci (brak setUser/user property)

**Testy funkcjonalne** (WebTestCase, prawdziwa baza danych):
- PhotoController (6 testow) - like/unlike toggle, guest redirect, 404, GET rejection,
  CSRF validation
- AuthController (7 testow) - login, session, logout, invalid credentials,
  mismatched token-user
- HomeController (3 testy) - galeria, guest vs logged in
- ProfileController (3 testy) - profil, guest redirect

Lacznie: 51 testow, 82+ asercje. Kazdy test jest niezalezny (F.I.R.S.T.) -
`cleanDatabase()` w setUp/tearDown.

## Co bym jeszcze poprawil majac wiecej czasu

- **Flush w repozytorium** (#22) - przeniesienie odpowiedzialnosci za `flush()`
  do warstwy serwisowej (Unit of Work pattern). Repozytorium powinno tylko
  `persist()`/`remove()`, a serwis decyduje kiedy commitowac
- **Publiczny setLikeCounter()** (#23) - enkapsulacja countera, usunac publiczny
  setter i zostawic tylko atomowe operacje SQL
- **DRY - wzorzec sesji** (#24) - powtarzajacy sie kod pobierania usera z sesji
  w kontrolerach. Wydzielenie do Symfony EventListener lub ArgumentValueResolver
- **Symfony Security** (#25) - reczna autentykacja przez sesje zastapiona
  pelnym Symfony Security z custom authenticatorem
- **Binding interfejsow** (#26) - przeglad services.yaml pod katem brakujacych
  bindow i optymalizacji

## Wspolpraca z AI

Do rozwiazania tego zadania uzylem Claude Code (CLI) - narzedzia AI-assisted coding
od Anthropic. Pracowalismy w modelu kooperacji: ja podejmowalem decyzje architektoniczne,
priorytetyzowalem bugi i weryfikowalem kazdy krok, Claude wykonywal implementacje,
pisal testy i zarzadzal workflow gitowym.

Konkretnie:
- **Moj wklad**: identyfikacja problemow, kategoryzacja wagi bugow, decyzje
  o podejsciu do naprawy, review kazdego PR przed merge, kontrola jakosci
- **Wklad AI**: implementacja fixow, pisanie testow, zarzadzanie branchami/PR/issues,
  aktualizacja statusow na project board

Calosc pracy jest przejrzysta w historii gitowej - kazdy commit, PR i komentarz
w review sa widoczne. Uwazam, ze umiejetnosc efektywnej wspolpracy z AI to istotna
kompetencja wspolczesnego developera - pozwala skupic sie na decyzjach architektonicznych
i jakosci, delegujac powtarzalna robote.

---

# Notatki - Zadanie 2: Import zdjec z PhoenixApi

[Tablica projektu](https://github.com/users/piotradamczyk78/projects/10)

## Podejscie do pracy

Funkcjonalnosc rozbilem na 5 atomowych krokow - kazdy w osobnym branchu z PR
do develop. Kazdy krok od razu zawiera testy pokrywajace brzegowe warunki.
Calosc sledzona na GitHub Project board ze statusami Todo / In Progress / Done.

## Wprowadzone zmiany

### 1. Migracja - pole phoenix_api_token (PR #65)

Nowa kolumna `phoenix_api_token` (varchar 255, nullable) w tabeli `users`.
Nullable, bo nie kazdy uzytkownik musi miec skonfigurowana integracje z PhoenixApi.
Getter/setter w encji User + migracja Doctrine.

### 2. Formularz tokenu w profilu (PR #66)

Endpoint `POST /profile/phoenix-token` z walidacja CSRF. Formularz w szablonie
profilu - pole tekstowe z przyciskiem Save. Pusty string czysci token na null.
Flash messages informuja o wyniku.

### 3. Serwis klienta PhoenixApi (PR #67)

`PhoenixApiClient` oparty na Symfony HttpClient. Metoda `fetchPhotos(token)`
wywoluje `GET /api/photos` z headerem `access-token`. Dedykowany wyjatek
`InvalidPhoenixTokenException` dla odpowiedzi 401. URL konfigurowalny przez
env `PHOENIX_BASE_URL` (uzywa istniejacego env z docker-compose).

### 4. Serwis importu zdjec (PR #68)

`PhotoImportService` z logika biznesowa importu. Pobiera zdjecia z PhoenixApi,
tworzy encje Photo mapujac `photo_url` -> `imageUrl`. Zapobiega duplikatom -
sprawdza istniejace URL-e usera i deduplikuje w ramach batcha. Zwraca
statystyki: ile zaimportowano / pominieto. Jeden flush na koniec batcha.

### 5. Przycisk importu w profilu (PR #69)

Endpoint `POST /profile/import-photos` z CSRF protection. Przycisk "Import Photos"
widoczny w profilu tylko gdy token PhoenixApi jest ustawiony. Obsluga trzech
scenariuszy bledow: bledny token (401), blad sieci, brak tokenu. Flash messages
z czytelnym komunikatem.

## Testy

30 nowych testow napisanych inline z kazdym krokiem:

**Testy jednostkowe:**
- PhoenixApiClientTest (7 testow) - success, empty, 401, 500, header, URL, brak klucza
- PhotoImportServiceTest (6 testow) - import, duplikaty, pusty response, puste URL, batch dedup
- UserTest (3 testy) - default null, set/get, clear to null

**Testy funkcjonalne:**
- ProfileControllerTest - zapis tokenu (5 testow) - guest, CSRF, success, clear, GET 405
- ProfileControllerTest - import (9 testow) - guest, CSRF, brak tokenu, success, invalid token,
  blad sieci, GET 405, widocznosc przycisku

Lacznie po Zadaniu 2: **81 testow, 155 asercji**.

## Decyzje architektoniczne

- **PHOENIX_BASE_URL zamiast PHOENIX_API_URL** - uzylem istniejacego env z docker-compose
  zamiast tworzenia nowego, zeby uniknac duplikacji konfiguracji
- **Deduplikacja na poziomie URL** - porownuje imageUrl per user, nie Phoenix photo ID,
  bo ID z PhoenixApi nie jest przechowywane w encji Photo
- **Jeden flush na batch** - zamiast flush per zdjecie, jeden flush po calym imporcie
  dla wydajnosci
- **Mock przez disableReboot()** - w testach funkcjonalnych uzycie `$client->disableReboot()`
  zeby mock PhotoImportService przetrwal miedzy requestami w ramach jednego testu

---

# Notatki - Zadanie 3: Filtrowanie zdjec na stronie glownej

[Tablica projektu](https://github.com/users/piotradamczyk78/projects/11)

## Podejscie do pracy

Funkcjonalnosc rozbilem na 4 atomowe kroki - kazdy w osobnym branchu z PR
do develop. Calosc sledzona na GitHub Project board.

## Wprowadzone zmiany

### 1. Metoda filtrowania w PhotoRepository (PR #71)

Nowa metoda `findByFilters(array $filters)` dynamicznie budujaca QueryBuilder.
Filtry LIKE dla tekstowych pol (location, camera, description, username)
i zakres dat dla `takenAt` (date_from / date_to). Eager-loading userow
przez LEFT JOIN. Gdy brak filtrow - zwraca wszystkie zdjecia.

### 2. Formularz filtrow w szablonie galerii (PR #72)

Formularz GET z 6 polami umieszczony nad siatka zdjec. Pola: location, camera,
description, username, date_from, date_to. Przycisk "Filter" + link "Clear"
(prosty href do strony glownej bez parametrow). Filtry w URL - bookmarkowalne
i udostepnialne.

### 3. Obsluga filtrow w HomeController (PR #73)

Odczyt parametrow z query string w `HomeController::index()`. Warunkowe
przekazanie do `findByFilters()` gdy filtry niepuste, w przeciwnym razie
`findAllWithUsers()`. Aktualne filtry przekazywane do szablonu - formularz
zachowuje wartosci pol po submicie.

### 4. Testy (PR #74)

19 nowych testow:

**PhotoRepositoryFilterTest** (10 testow, KernelTestCase):
- kazdy filtr osobno (location, camera, description, username, date_from, date_to)
- zakres dat, kombinacja filtrow, puste filtry, brak wynikow

**HomeControllerTest** (9 nowych testow, WebTestCase):
- filtrowanie end-to-end po kazdym polu
- kombinacja filtrow, brak wynikow (empty state), zachowanie wartosci formularza

Lacznie po Zadaniu 3: **100 testow, 214 asercji**.

## Decyzje architektoniczne

- **GET zamiast POST** - filtry w query string sa bookmarkowalne i mozna je
  udostepniac jako link. Nie modyfikuja stanu serwera
- **LIKE z wildcardami** - czesciowe dopasowanie (`%value%`) pozwala na wygodne
  wyszukiwanie bez znajomosci pelnej nazwy
- **Optymalizacja bez filtrow** - gdy brak filtrow uzywam prostszego
  `findAllWithUsers()` zamiast `findByFilters()` z pustymi parametrami
- **date_to z 23:59:59** - doliczenie czasu do konca dnia, zeby wlaczac
  zdjecia z calego dnia koncowego zakresu
- **Testy repozytorium jako KernelTestCase** - repozytorium Doctrine wymaga
  prawdziwego EntityManagera, ale nie potrzebuje HTTP clienta

---

# Notatki - Zadanie 4: Rate-limiting w PhoenixApi (OTP)

[Tablica projektu](https://github.com/users/piotradamczyk78/projects/12)

## Podejscie do pracy

Funkcjonalnosc rozbilem na 5 atomowych krokow - 3 w Phoenix (Elixir/OTP)
i 2 w Symfony (PHP). Kazdy w osobnym branchu z PR do develop.

## Wprowadzone zmiany

### 1. RateLimiter GenServer z ETS (PR #76)

`PhoenixApi.RateLimiter` - GenServer startowany w supervision tree.
ETS tabela z timestampami requestow per key (sliding window).
API: `check_rate(key, limit, window_ms)` zwraca `:ok` lub
`{:error, :rate_limited, retry_after_s}`. Periodyczny cleanup
starych wpisow co 5 minut.

### 2. Plug rate-limitingu (PR #77)

`PhoenixApiWeb.Plugs.RateLimit` sprawdzajacy dwa limity:
- per-user (access-token): max 5 requestow / 10 min
- globalny: max 1000 requestow / 1h

Przy przekroczeniu: 429 Too Many Requests z JSON body
i headerem `Retry-After`.

### 3. Podpiecie do routera Phoenix (PR #78)

Pipeline `:rate_limited` z plugiem RateLimit dolaczony do scope `/api`.
Rate-limiting sprawdzany przed autentykacja - chroni przed brute-force
na tokeny.

### 4. Obsluga 429 w Symfony (PR #79)

Dedykowany wyjatek `RateLimitExceededException` z `retryAfter`.
`PhoenixApiClient` rzuca wyjatek przy odpowiedzi 429, odczytuje
`Retry-After` z headera. `ProfileController` wyswietla komunikat
"Too many imports, please try again in X seconds".

### 5. Testy (PR #80)

12 nowych testow:

**RateLimiterTest** (6 testow, ExUnit):
- w limicie, przekroczenie, niezalezne klucze, reset po wygasnieciu okna,
  cleanup wpisow, retry_after minimum 1 sekunda

**RateLimitPlugTest** (3 testy, ConnCase):
- 200 w limicie, 429 po przekroczeniu per-user, niezaleznosc limitow

**PhoenixApiClientTest** (2 nowe testy, PHPUnit):
- 429 z Retry-After header, domyslny fallback 60s

**ProfileControllerTest** (1 nowy test, WebTestCase):
- komunikat rate limit z retry_after w UI

Lacznie po Zadaniu 4: Phoenix **15 testow**, Symfony **103 testow, 220 asercji**.

## Decyzje architektoniczne

- **ETS zamiast bazy danych** - szybki dostep do timestampow bez obciazania
  bazy. Rate-limiting nie wymaga trwalosci - restart serwera resetuje limity
- **Sliding window** - dokladniejszy niz fixed window, nie dopuszcza
  podwojnego limitu na granicy okien
- **GenServer z nazwana instancja** - parametr `name` pozwala na testowanie
  z izolowanymi instancjami (kazdy test ma wlasny GenServer)
- **Rate key per access-token** - plug dziala przed autentykacja, wiec
  nie zna jeszcze user_id. Token jest unikatowy per user
- **RateLimitExceededException rozszerza RuntimeException** - wymaga
  uchwycenia PRZED generycznym `catch (\RuntimeException)` w kontrolerze
- **Restart RateLimiter w testach plug** - globalny singleton wymaga
  czyszczenia stanu miedzy testami. Supervisor automatycznie restartuje
  proces po `GenServer.stop()`
