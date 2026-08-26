import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { getToken } from "@/lib/api";

type AuthContext = {
  ready: boolean;
  authed: boolean;
  setAuthed: (authed: boolean) => void;
};

const Context = createContext<AuthContext | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [authed, setAuthed] = useState(false);

  useEffect(() => {
    getToken().then((token) => {
      setAuthed(!!token);
      setReady(true);
    });
  }, []);

  return <Context.Provider value={{ ready, authed, setAuthed }}>{children}</Context.Provider>;
}

export function useAuth() {
  const ctx = useContext(Context);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
