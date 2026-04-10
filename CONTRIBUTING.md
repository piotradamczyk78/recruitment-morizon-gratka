# Reguły kontrybucji

> Dokument definiuje standardy pracy i konwencje stosowane w projekcie.

---

## Git Workflow

### Strategia branching

```
main ──────────────────────────────► (stabilna wersja)
  │
  └── develop ─────────────────────► (integracja)
        │
        ├── feature/zadanie-XX-nazwa ► PR ─► merge
        │
        ├── fix/opis-problemu ───────► PR ─► merge
        │
        ├── test/opis-testow ────────► PR ─► merge
        │
        └── review/code-review ──────► PR ─► merge
```

### Nazewnictwo branchy

```bash
feature/task-02-photo-import
fix/sql-injection-auth
fix/race-condition-like-counter
test/task-01-unit-tests
test/task-01-functional-tests
review/code-review-task-01
refactor/extract-current-user-provider
```

---

## Konwencja commitów

### Format (Conventional Commits)

```
<type>(<scope>): <description>

[optional body]
```

### Typy

| Typ | Opis |
|-----|------|
| `feat` | Nowa funkcjonalność |
| `fix` | Naprawa błędu |
| `refactor` | Refaktoryzacja bez zmiany zachowania |
| `docs` | Dokumentacja |
| `test` | Testy |
| `chore` | Konfiguracja, tooling |
| `style` | Formatowanie |

### Scope

`auth`, `likes`, `photos`, `backend`, `api`, `db`, `docker`, `phoenix`

### Przykłady

```bash
fix(auth): usunięcie SQL injection w AuthController
fix(likes): atomowa transakcja w LikeRepository
feat(backend): import zdjęć z PhoenixApi
test(backend): testy jednostkowe dla LikeService
refactor(auth): wydzielenie CurrentUserProvider
docs(readme): dodanie instrukcji instalacji
chore(docker): konfiguracja healthcheck dla PostgreSQL
```

### Zasady

1. **Atomowość** — jeden commit = jedna logiczna zmiana
2. **Język polski** — opisy commitów w języku polskim
3. **Typ angielski** — typy (feat, fix, etc.) pozostają po angielsku
4. **Max 72 znaki** — w pierwszej linii
5. **Bez kropki** — na końcu opisu

---

## Quality Gates

### Przed commitem

```bash
# Uruchomienie testów w kontenerze
docker exec -it symfony php bin/phpunit
```

### Wymagania

- Testy — 100% pass
- Brak `dd()`, `dump()`, `var_dump()` w kodzie
- Brak hardkodowanych credentials
- Jeden PR = jedna logiczna zmiana

---

## Testy

### Struktura

```
tests/
├── Unit/                    # Testy jednostkowe (mocki, bez bazy)
│   ├── Controller/
│   ├── Entity/
│   ├── Likes/
│   └── Security/
└── Functional/              # Testy funkcjonalne (WebTestCase, prawdziwa baza)
    ├── FunctionalTestCase.php  # Klasa bazowa z helperami
    ├── AuthFlowTest.php
    ├── GalleryTest.php
    ├── LikeFlowTest.php
    └── ProfileTest.php
```

### Zasady

1. **Unit testy** — izolowane, używają mocków, bez dostępu do bazy
2. **Functional testy** — pełny HTTP flow przez Symfony kernel z testową bazą danych
3. **Nazewnictwo** — `test<CoTestujemy><OczekiwanyRezultat>()`, np. `testLikeWithInvalidCsrfTokenIsRejected()`
4. **Priorytet** — testy pisane od najkrytyczniejszych bugów
5. **Pokrycie** — każdy fix musi mieć odpowiadający test

### Uruchamianie

```bash
# Wszystkie testy
docker exec -it symfony php bin/phpunit

# Tylko jednostkowe
docker exec -it symfony php bin/phpunit tests/Unit

# Tylko funkcjonalne
docker exec -it symfony php bin/phpunit tests/Functional

# Konkretny plik
docker exec -it symfony php bin/phpunit tests/Unit/Likes/LikeServiceTest.php
```

---

## Dokumentacja kodu

### Język

- **Komentarze w kodzie** — po angielsku (spójność z oryginalnym kodem projektu)
- **Commity, PR-y, issues** — po polsku (zadanie rekrutacyjne otrzymane w języku polskim, więc komunikacja projektowa pozostaje w tym samym języku)

### PHP (PHPDoc)

```php
/**
 * Class description.
 */
final class ExampleService
{
    /**
     * Method description.
     *
     * @param Type $param Parameter description
     * @return ReturnType Return value description
     * @throws ExceptionType When exception is thrown
     */
    public function method(Type $param): ReturnType
    {
        // ...
    }
}
```

---

## Pull Request

### Szablon

```markdown
## Summary
- Opis głównych zmian

## Zmiany
- `ścieżka/do/pliku.php` — opis zmiany

## Decyzje techniczne
- Uzasadnienie wyborów architektonicznych

## Test plan
- [ ] Testy jednostkowe przechodzą
- [ ] Testy funkcjonalne przechodzą
- [ ] Brak dd()/dump() w kodzie
```

### Przykładowe tytuły PR

```
fix(auth): usunięcie SQL injection w AuthController
test(backend): testy jednostkowe dla warstwy serwisów i encji
docs: zasady kontrybucji
```

---

## Nazewnictwo

### PHP

| Element | Konwencja | Przykład |
|---------|-----------|----------|
| Klasa | PascalCase | `LikeService` |
| Interface | PascalCase + Interface | `LikeRepositoryInterface` |
| Metoda | camelCase | `toggleLike()` |
| Zmienna | camelCase | `$likeCounter` |
| Stała | UPPER_SNAKE | `MAX_RETRIES` |

### Elixir

| Element | Konwencja | Przykład |
|---------|-----------|----------|
| Moduł | PascalCase | `PhoenixApi.Media.Photo` |
| Funkcja | snake_case | `list_photos/0` |
| Zmienna | snake_case | `photo_count` |

---

## Checklist przed mergem

- [ ] Testy przechodzą (`docker exec -it symfony php bin/phpunit`)
- [ ] Brak `dd()`, `dump()`, `var_dump()` w kodzie
- [ ] Brak hardkodowanych credentials
- [ ] PR ma opis zmian i plan testowania
- [ ] Commit message zgodny z konwencją
- [ ] Jeden PR = jedna logiczna zmiana
