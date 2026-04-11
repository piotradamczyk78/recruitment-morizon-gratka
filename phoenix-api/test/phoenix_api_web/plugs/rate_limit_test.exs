defmodule PhoenixApiWeb.Plugs.RateLimitTest do
  use PhoenixApiWeb.ConnCase

  alias PhoenixApi.Repo
  alias PhoenixApi.Accounts.User

  setup do
    # Restart RateLimiter to clear state between tests
    if pid = Process.whereis(PhoenixApi.RateLimiter) do
      GenServer.stop(pid)
      Process.sleep(50)
    end

    # Wait for supervisor to restart it
    wait_for_rate_limiter(10)

    user =
      %User{}
      |> User.changeset(%{api_token: "rate_limit_test_token"})
      |> Repo.insert!()

    {:ok, user: user}
  end

  defp wait_for_rate_limiter(0), do: raise("RateLimiter did not restart")
  defp wait_for_rate_limiter(retries) do
    case Process.whereis(PhoenixApi.RateLimiter) do
      nil ->
        Process.sleep(50)
        wait_for_rate_limiter(retries - 1)
      _pid -> :ok
    end
  end

  test "allows request within rate limit", %{conn: conn} do
    conn =
      conn
      |> put_req_header("access-token", "rate_limit_test_token")
      |> get("/api/photos")

    assert json_response(conn, 200)
  end

  test "returns 429 when user rate limit exceeded", %{conn: conn} do
    # User limit is 5 per 10 minutes
    for _ <- 1..5 do
      conn
      |> put_req_header("access-token", "rate_limit_test_token")
      |> get("/api/photos")
    end

    conn =
      conn
      |> put_req_header("access-token", "rate_limit_test_token")
      |> get("/api/photos")

    assert json_response(conn, 429)
    assert %{"error" => "Too Many Requests", "retry_after" => _} = json_response(conn, 429)
    assert get_resp_header(conn, "retry-after") |> List.first() |> String.to_integer() > 0
  end

  test "different users have independent rate limits", %{conn: conn} do
    _other_user =
      %User{}
      |> User.changeset(%{api_token: "other_rate_limit_token"})
      |> Repo.insert!()

    # Exhaust first user's limit
    for _ <- 1..5 do
      conn
      |> put_req_header("access-token", "rate_limit_test_token")
      |> get("/api/photos")
    end

    # Other user should still be allowed
    other_conn =
      conn
      |> put_req_header("access-token", "other_rate_limit_token")
      |> get("/api/photos")

    assert json_response(other_conn, 200)
  end
end
