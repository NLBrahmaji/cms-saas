export function AuthLoadingScreen({ message = "Loading…" }: { message?: string }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-50 px-4 dark:bg-zinc-950">
      <p className="text-sm text-zinc-600 dark:text-zinc-400">{message}</p>
    </div>
  );
}
