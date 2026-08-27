import { useRef, useState } from "react";
import { Pressable, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { BottomSheetModal, BottomSheetView } from "@gorhom/bottom-sheet";
import { AVATARS } from "@/lib/avatars";
import { updateProfile, type Profile } from "@/lib/api";
import { SaveToast, type SaveToastHandle } from "@/components/ui/save-toast";
import { Sheet } from "@/components/ui/sheet";

/** Grid of predefined avatars — no photo upload. Tapping one saves immediately. */
export function AvatarPickerSheet({
  currentAvatar,
  onClose,
  onSaved,
}: {
  currentAvatar: string | null;
  onClose: () => void;
  onSaved: (profile: Profile) => void;
}) {
  const sheetRef = useRef<BottomSheetModal>(null);
  const saveToastRef = useRef<SaveToastHandle>(null);
  const [saving, setSaving] = useState<string | null>(null);

  async function pick(id: string | null) {
    setSaving(id ?? "none");
    saveToastRef.current?.showSaving();
    try {
      const updated = await updateProfile({ avatar: id });
      onSaved(updated);
      saveToastRef.current?.showSuccess();
      // Let the confirmation flash briefly before the sheet closes itself.
      setTimeout(() => sheetRef.current?.dismiss(), 500);
    } catch {
      saveToastRef.current?.showError();
    } finally {
      setSaving(null);
    }
  }

  return (
    <Sheet ref={sheetRef} onDismiss={onClose}>
      <BottomSheetView style={{ paddingHorizontal: 24, paddingBottom: 32 }}>
        <Text className="mb-1 text-lg font-semibold text-ink">Choisis ton avatar</Text>
        <Text className="mb-4 text-sm text-ink opacity-60">
          Pas de photo pour l'instant — juste un symbole qui te représente.
        </Text>

        <View className="-mb-3 -mr-3 flex-row flex-wrap">
          {AVATARS.map((avatar) => {
            const selected = currentAvatar === avatar.id;
            return (
              <Pressable
                key={avatar.id}
                onPress={() => pick(avatar.id)}
                disabled={saving !== null}
                accessibilityRole="button"
                accessibilityState={{ selected }}
                className="mb-3 mr-3 h-16 w-16 items-center justify-center rounded-full"
                style={{
                  backgroundColor: `${avatar.color}1F`,
                  borderWidth: selected ? 2 : 0,
                  borderColor: avatar.color,
                }}
              >
                <Ionicons name={avatar.icon} size={28} color={avatar.color} />
              </Pressable>
            );
          })}
        </View>

        {currentAvatar != null && (
          <Pressable
            onPress={() => pick(null)}
            disabled={saving !== null}
            className="mt-1 h-11 items-center justify-center"
            accessibilityRole="button"
          >
            <Text className="text-sm font-semibold text-ink opacity-70">
              Revenir à mes initiales
            </Text>
          </Pressable>
        )}
      </BottomSheetView>
      <SaveToast ref={saveToastRef} />
    </Sheet>
  );
}
