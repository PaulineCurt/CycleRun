import { useState } from "react";
import { Card } from "@/components/ui/card";
import { CardHeader } from "@/components/ui/card-header";
import { Button } from "@/components/ui/button";
import { DayFeelingSheet } from "@/components/DayFeelingSheet";
import { COLOR } from "@/lib/colors";

const BADGE = COLOR.badge;

/**
 * The answer to "nothing happens at day 0": a single entry point into the full
 * feeling sheet (energy, pain, symptoms by body area) — no navigation, opens inline.
 * Menstrual flow itself is logged from the calendar, not from here.
 */
export function DailyFeelingCard() {
  const [open, setOpen] = useState(false);

  return (
    <Card>
      <CardHeader icon="happy-outline" color={BADGE} label="Comment tu te sens aujourd'hui ?" />
      <Button
        variant="secondary"
        icon="add-circle-outline"
        label="Noter mon ressenti"
        onPress={() => setOpen(true)}
        className="mt-2"
      />
      {open && <DayFeelingSheet onClose={() => setOpen(false)} />}
    </Card>
  );
}
