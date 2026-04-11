defmodule PhoenixApiWeb.Router do
  use PhoenixApiWeb, :router

  pipeline :api do
    plug :accepts, ["json"]
  end

  pipeline :rate_limited do
    plug PhoenixApiWeb.Plugs.RateLimit
  end

  scope "/api", PhoenixApiWeb do
    pipe_through [:api, :rate_limited]

    get "/photos", PhotoController, :index
  end
end
