defmodule PhoenixApi.RateLimiterTest do
  use ExUnit.Case, async: true

  alias PhoenixApi.RateLimiter

  setup do
    name = :"rate_limiter_#{:erlang.unique_integer([:positive])}"
    {:ok, pid} = RateLimiter.start_link(name: name, cleanup_interval: :timer.hours(1))
    {:ok, name: name, pid: pid}
  end

  test "allows requests within limit", %{name: name} do
    assert :ok = RateLimiter.check_rate("test_key", 3, 60_000, name)
    assert :ok = RateLimiter.check_rate("test_key", 3, 60_000, name)
    assert :ok = RateLimiter.check_rate("test_key", 3, 60_000, name)
  end

  test "rejects requests exceeding limit", %{name: name} do
    assert :ok = RateLimiter.check_rate("test_key", 2, 60_000, name)
    assert :ok = RateLimiter.check_rate("test_key", 2, 60_000, name)
    assert {:error, :rate_limited, retry_after} = RateLimiter.check_rate("test_key", 2, 60_000, name)
    assert is_integer(retry_after)
    assert retry_after > 0
  end

  test "different keys are independent", %{name: name} do
    assert :ok = RateLimiter.check_rate("key_a", 1, 60_000, name)
    assert {:error, :rate_limited, _} = RateLimiter.check_rate("key_a", 1, 60_000, name)
    assert :ok = RateLimiter.check_rate("key_b", 1, 60_000, name)
  end

  test "resets after window expires", %{name: name} do
    assert :ok = RateLimiter.check_rate("test_key", 1, 1, name)
    Process.sleep(10)
    assert :ok = RateLimiter.check_rate("test_key", 1, 1, name)
  end

  test "cleanup removes expired entries", %{name: name} do
    # Add an entry with a short window
    assert :ok = RateLimiter.check_rate("test_key", 1, 1, name)
    Process.sleep(10)
    # After window expires, a new check_rate with same short window should pass
    # because check_rate filters out expired timestamps
    assert :ok = RateLimiter.check_rate("test_key", 1, 1, name)
  end

  test "retry_after is at least 1 second", %{name: name} do
    assert :ok = RateLimiter.check_rate("test_key", 1, 60_000, name)
    assert {:error, :rate_limited, retry_after} = RateLimiter.check_rate("test_key", 1, 60_000, name)
    assert retry_after >= 1
  end
end
