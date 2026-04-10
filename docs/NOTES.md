# Notatki - Zadanie 1: Poprawa jakosci kodu

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
