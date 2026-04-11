defmodule PhoenixApi.RateLimiter do
  use GenServer

  @cleanup_interval :timer.minutes(5)

  # Client API

  def start_link(opts \\ []) do
    name = Keyword.get(opts, :name, __MODULE__)
    GenServer.start_link(__MODULE__, opts, name: name)
  end

  def check_rate(key, limit, window_ms, name \\ __MODULE__) do
    GenServer.call(name, {:check_rate, key, limit, window_ms})
  end

  # Server callbacks

  @impl true
  def init(opts) do
    table = :ets.new(:rate_limiter, [:set, :protected])
    cleanup_interval = Keyword.get(opts, :cleanup_interval, @cleanup_interval)
    Process.send_after(self(), :cleanup, cleanup_interval)
    {:ok, %{table: table, cleanup_interval: cleanup_interval}}
  end

  @impl true
  def handle_call({:check_rate, key, limit, window_ms}, _from, state) do
    now = System.monotonic_time(:millisecond)
    cutoff = now - window_ms

    timestamps =
      case :ets.lookup(state.table, key) do
        [{^key, ts_list}] -> Enum.filter(ts_list, fn ts -> ts > cutoff end)
        [] -> []
      end

    if length(timestamps) < limit do
      :ets.insert(state.table, {key, [now | timestamps]})
      {:reply, :ok, state}
    else
      oldest = Enum.min(timestamps)
      retry_after_ms = oldest + window_ms - now
      retry_after_s = max(1, ceil(retry_after_ms / 1000))
      {:reply, {:error, :rate_limited, retry_after_s}, state}
    end
  end

  @impl true
  def handle_info(:cleanup, state) do
    now = System.monotonic_time(:millisecond)
    max_window = :timer.hours(1)
    cutoff = now - max_window

    :ets.foldl(
      fn {key, timestamps}, _acc ->
        filtered = Enum.filter(timestamps, fn ts -> ts > cutoff end)

        if filtered == [] do
          :ets.delete(state.table, key)
        else
          :ets.insert(state.table, {key, filtered})
        end
      end,
      nil,
      state.table
    )

    Process.send_after(self(), :cleanup, state.cleanup_interval)
    {:noreply, state}
  end
end
