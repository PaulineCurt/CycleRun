import { useState } from "react";
import { Modal, Pressable, Text } from "react-native";
import { toDateKey } from "@/lib/calendar";
import {
  createCycle,
  deleteCycle,
  setMensesEndedOn,
  type CalendarDay,
} from "@/lib/api";

export function DayActionModal({
  date,
  day,
  onClose,
  onSaved,
}: {
  date: Date;
  day: CalendarDay | undefined;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const dateKey = toDateKey(date);
  const label = date.toLocaleDateString("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
  });

  async function run(action: () => Promise<unknown>) {
    setError(null);
    setSaving(true);
    try {
      await action();
      onSaved();
    } catch {
      setError("Impossible d'enregistrer. Réessaie dans un instant.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal transparent animationType="fade" visible onRequestClose={onClose}>
      <Pressable
        className="flex-1 justify-end bg-ink/40"
        onPress={onClose}
        accessibilityLabel="Fermer"
      >
        <Pressable
          className="rounded-t-2xl bg-surface p-6"
          onPress={(event) => event.stopPropagation()}
        >
          <Text className="mb-4 text-lg font-semibold capitalize text-ink">{label}</Text>

          {day?.is_cycle_start ? (
            <Pressable
              onPress={() => run(() => deleteCycle(day.cycle_id!))}
              disabled={saving}
              className="mb-3 h-11 items-center justify-center rounded-lg border-2 border-danger"
              accessibilityRole="button"
            >
              <Text className="font-semibold text-danger">
                {saving ? "..." : "Annuler ce début de règles"}
              </Text>
            </Pressable>
          ) : (
            <Pressable
              onPress={() => run(() => createCycle(dateKey))}
              disabled={saving}
              className="mb-3 h-11 items-center justify-center rounded-lg bg-accent"
              accessibilityRole="button"
            >
              <Text className="font-semibold text-ink">
                {saving ? "..." : "Début de mes règles"}
              </Text>
            </Pressable>
          )}

          {day?.is_menses_end ? (
            <Pressable
              onPress={() => run(() => setMensesEndedOn(day.cycle_id!, null))}
              disabled={saving}
              className="mb-3 h-11 items-center justify-center rounded-lg border-2 border-danger"
              accessibilityRole="button"
            >
              <Text className="font-semibold text-danger">
                {saving ? "..." : "Annuler cette fin de règles"}
              </Text>
            </Pressable>
          ) : (
            day?.cycle_id != null && (
              <Pressable
                onPress={() => run(() => setMensesEndedOn(day.cycle_id!, dateKey))}
                disabled={saving}
                className="mb-3 h-11 items-center justify-center rounded-lg bg-ink/6"
                accessibilityRole="button"
              >
                <Text className="font-semibold text-ink">
                  {saving ? "..." : "Fin de mes règles"}
                </Text>
              </Pressable>
            )
          )}

          {error && <Text className="mb-3 text-danger">{error}</Text>}

          <Pressable
            onPress={onClose}
            className="h-11 items-center justify-center"
            accessibilityRole="button"
          >
            <Text className="text-center text-ink opacity-70">Annuler</Text>
          </Pressable>
        </Pressable>
      </Pressable>
    </Modal>
  );
}
