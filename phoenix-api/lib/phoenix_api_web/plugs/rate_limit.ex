defmodule PhoenixApiWeb.Plugs.RateLimit do
  import Plug.Conn

  @user_limit 5
  @user_window_ms :timer.minutes(10)
  @global_limit 1000
  @global_window_ms :timer.hours(1)

  def init(opts), do: opts

  def call(conn, _opts) do
    user_key = user_rate_key(conn)

    with :ok <- check_limit(user_key, @user_limit, @user_window_ms),
         :ok <- check_limit("global", @global_limit, @global_window_ms) do
      conn
    else
      {:error, :rate_limited, retry_after} ->
        conn
        |> put_resp_header("retry-after", Integer.to_string(retry_after))
        |> put_resp_content_type("application/json")
        |> send_resp(429, Jason.encode!(%{error: "Too Many Requests", retry_after: retry_after}))
        |> halt()
    end
  end

  defp check_limit(key, limit, window_ms) do
    PhoenixApi.RateLimiter.check_rate(key, limit, window_ms)
  end

  defp user_rate_key(conn) do
    case get_req_header(conn, "access-token") do
      [token] -> "user:#{token}"
      [] -> "anonymous:#{remote_ip(conn)}"
    end
  end

  defp remote_ip(conn) do
    conn.remote_ip |> :inet.ntoa() |> to_string()
  end
end
