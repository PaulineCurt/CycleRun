import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from "react";
import { Animated, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { BottomSheetModal, BottomSheetView } from "@gorhom/bottom-sheet";
import { ColorfulSpinner } from "@/components/ui/colorful-spinner";
import { Sheet } from "@/components/ui/sheet";
import { COLOR } from "@/lib/colors";

type Status = "saving" | "success" | "error";

export type SaveToastHandle = {
  /** Shows the spinner. Call `showSuccess`/`showError` once the request settles. */
  showSaving: () => void;
  showSuccess: (message?: string) => void;
  showError: (message?: string) => void;
};

const SUCCESS_AUTO_HIDE_MS = 1400;
const ICON_SIZE = 64;

const STATUS_COLOR: Record<Status, string> = {
  saving: COLOR.ink,
  success: COLOR.accent,
  error: COLOR.danger,
};

/**
 * A bold bottom toast for saves that might take a moment: the app's colorful
 * rotating ring while the request is in flight, then a brief checkmark
 * confirmation before it dismisses itself. Built on @gorhom/bottom-sheet (not a
 * plain RN <Modal>) so it reliably renders above the tab bar on native — same
 * reason DayFeelingSheet/DayActionModal moved off <Modal> earlier.
 */
export const SaveToast = forwardRef<SaveToastHandle>((_props, ref) => {
  const sheetRef = useRef<BottomSheetModal>(null);
  const [status, setStatus] = useState<Status>("saving");
  const [message, setMessage] = useState<string | null>(null);
  const scale = useRef(new Animated.Value(0)).current;
  const hideTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useImperativeHandle(ref, () => ({
    showSaving: () => {
      if (hideTimer.current) clearTimeout(hideTimer.current);
      setStatus("saving");
      setMessage(null);
      sheetRef.current?.present();
    },
    showSuccess: (text) => {
      setStatus("success");
      setMessage(text ?? "Enregistré !");
      hideTimer.current = setTimeout(() => sheetRef.current?.dismiss(), SUCCESS_AUTO_HIDE_MS);
    },
    showError: (text) => {
      setStatus("error");
      setMessage(text ?? "Une erreur est survenue.");
      hideTimer.current = setTimeout(() => sheetRef.current?.dismiss(), SUCCESS_AUTO_HIDE_MS);
    },
  }));

  useEffect(() => {
    if (status === "saving") return;
    scale.setValue(0);
    Animated.spring(scale, { toValue: 1, useNativeDriver: true, friction: 5, tension: 140 }).start();
  }, [status, scale]);

  useEffect(() => () => {
    if (hideTimer.current) clearTimeout(hideTimer.current);
  }, []);

  const color = STATUS_COLOR[status];

  return (
    <Sheet ref={sheetRef} backdrop={false} autoPresent={false} enablePanDownToClose>
      <BottomSheetView style={{ paddingHorizontal: 24, paddingBottom: 44, paddingTop: 12 }}>
        <View className="items-center">
          {status === "saving" ? (
            <ColorfulSpinner />
          ) : (
            <View
              style={{
                width: ICON_SIZE,
                height: ICON_SIZE,
                borderRadius: ICON_SIZE / 2,
                alignItems: "center",
                justifyContent: "center",
                backgroundColor: `${color}22`,
              }}
            >
              <Animated.View style={{ transform: [{ scale }] }}>
                <Ionicons
                  name={status === "success" ? "checkmark-circle" : "alert-circle"}
                  size={36}
                  color={color}
                />
              </Animated.View>
            </View>
          )}
          <Text className="mt-3 text-lg font-bold" style={{ color }}>
            {status === "saving" ? "Enregistrement..." : message}
          </Text>
        </View>
      </BottomSheetView>
    </Sheet>
  );
});
SaveToast.displayName = "SaveToast";
