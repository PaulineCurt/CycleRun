import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef, type ReactNode } from "react";
import {
  BottomSheetBackdrop,
  BottomSheetModal,
  type BottomSheetBackdropProps,
  type BottomSheetModalProps,
} from "@gorhom/bottom-sheet";

export type SheetProps = Omit<BottomSheetModalProps, "backdropComponent" | "backgroundStyle" | "handleIndicatorStyle"> & {
  children: ReactNode;
  /** Set false for a non-blocking toast that shouldn't dim/block the screen behind it. */
  backdrop?: boolean;
  /** Set false when the caller presents imperatively instead of showing on mount
   *  (e.g. SaveToast, which stays hidden until showSaving() is called). */
  autoPresent?: boolean;
};

/**
 * Thin wrapper around @gorhom/bottom-sheet's BottomSheetModal: bundles the app's
 * standard backdrop + styling, and presents itself on mount. Every sheet in the app
 * (DayFeelingSheet, DayActionModal, AvatarPickerSheet, SaveToast) used to repeat
 * this same boilerplate.
 */
export const Sheet = forwardRef<BottomSheetModal, SheetProps>(
  ({ children, backdrop = true, autoPresent = true, ...props }, ref) => {
    const internalRef = useRef<BottomSheetModal>(null);
    useImperativeHandle(ref, () => internalRef.current as BottomSheetModal);

    useEffect(() => {
      if (autoPresent) internalRef.current?.present();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const renderBackdrop = useCallback(
      (backdropProps: BottomSheetBackdropProps) => (
        <BottomSheetBackdrop {...backdropProps} appearsOnIndex={0} disappearsOnIndex={-1} pressBehavior="close" />
      ),
      [],
    );

    return (
      <BottomSheetModal
        ref={internalRef}
        backdropComponent={backdrop ? renderBackdrop : undefined}
        backgroundStyle={{ backgroundColor: "#FFFFFF" }}
        handleIndicatorStyle={{ backgroundColor: "#12302B26" }}
        {...props}
      >
        {children}
      </BottomSheetModal>
    );
  },
);
Sheet.displayName = "Sheet";
