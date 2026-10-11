import {
  addToast,
  Button,
  Card,
  CardBody,
  Input,
  Select,
  SelectItem,
} from "@heroui/react";
import Image from "next/image";
import { FC, useState } from "react";
import { useTranslation } from "react-i18next";
import { TippingConfig } from "@/types/ApiResponse";
import { useSettings } from "@/contexts/SettingsContext";
import { getTipPresets, validateTipAmount } from "@/helpers/riderTip";
import { formatAmount } from "@/helpers/functionalHelpers";

const PRESET_EMOJIS = ["😀", "☺️", "😍"];

interface TipSectionProps {
  config: TippingConfig;
  /** Tip currently saved on the cart/order. */
  currentTip: number;
  /**
   * "cart": every selection is submitted immediately and REPLACES the saved
   * tip (0 clears it — tapping the selected preset again deselects).
   * "order": the selection is held locally and ADDED to the existing tip
   * only when the customer confirms, since post-order tips are charged
   * separately via `paymentMethods`.
   */
  mode: "cart" | "order";
  onSubmit: (amount: number, paymentMethod?: string) => Promise<boolean>;
  isDisabled?: boolean;
  /** order mode only: tip payment options (COD is never among them). */
  paymentMethods?: string[];
}

const TipSection: FC<TipSectionProps> = ({
  config,
  currentTip,
  mode,
  onSubmit,
  isDisabled = false,
  paymentMethods = [],
}) => {
  const { t } = useTranslation();
  const { currencySymbol } = useSettings();
  const presets = getTipPresets(config);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isCustomOpen, setIsCustomOpen] = useState(false);
  const [customValue, setCustomValue] = useState("");
  // order mode: amount pending confirmation
  const [pendingAmount, setPendingAmount] = useState<number | null>(null);
  const [paymentMethod, setPaymentMethod] = useState(paymentMethods[0] || "");

  const isCartMode = mode === "cart";
  const busy = isSubmitting || isDisabled;

  // In cart mode the selection mirrors what the server has; in order mode
  // it's the local not-yet-confirmed choice.
  const selectedAmount = isCartMode ? currentTip : (pendingAmount ?? 0);
  const isCustomSelected =
    isCustomOpen || (selectedAmount > 0 && !presets.includes(selectedAmount));

  const validationMessage = (amount: number): string | null => {
    const verdict = validateTipAmount(amount, config);
    if (verdict === "ok") return null;
    if (verdict === "below_min")
      return t("tip.minAmount", {
        amount: `${currencySymbol}${formatAmount(config.min_tip_amount)}`,
      });
    if (verdict === "above_max")
      return t("tip.maxAmount", {
        amount: `${currencySymbol}${formatAmount(config.max_tip_amount)}`,
      });
    return t("tip.invalidAmount");
  };

  const submit = async (amount: number, method?: string) => {
    setIsSubmitting(true);
    try {
      const ok = await onSubmit(amount, method);
      if (ok) {
        setIsCustomOpen(false);
        setCustomValue("");
        setPendingAmount(null);
      }
      return ok;
    } finally {
      setIsSubmitting(false);
    }
  };

  const handlePresetPress = (amount: number) => {
    setIsCustomOpen(false);
    setCustomValue("");
    if (isCartMode) {
      // Tapping the active preset removes the tip.
      submit(selectedAmount === amount ? 0 : amount);
    } else {
      setPendingAmount(pendingAmount === amount ? null : amount);
    }
  };

  // Live validation for the custom field. Passed to the Input as a
  // controlled error so HeroUI shows OUR translated message instead of the
  // browser's native min/max text ("Value must be less than or equal to…").
  const customError = customValue
    ? validationMessage(parseFloat(customValue))
    : null;

  const handleCustomApply = () => {
    const amount = parseFloat(customValue);
    const error = validationMessage(amount);
    if (error) {
      addToast({ title: error, color: "warning" });
      return;
    }
    if (isCartMode) {
      submit(amount);
    } else {
      setPendingAmount(amount);
      setIsCustomOpen(false);
    }
  };

  const confirmDisabled =
    !pendingAmount ||
    validateTipAmount(pendingAmount, config) !== "ok" ||
    !paymentMethod;

  return (
    <Card radius="md" shadow="sm" className="w-full">
      <CardBody className="p-4 space-y-4">
        <div className="flex items-start gap-3">
          <Image
            src="/cart/rider_tip.png"
            alt=""
            width={56}
            height={56}
            className="w-14 h-14 shrink-0 object-contain"
          />
          <div>
            <h3 className="font-semibold">{t("tip.sectionTitle")}</h3>
            <p className="text-sm text-default-500 mt-1">
              {t("tip.sectionDescription")}
            </p>
          </div>
        </div>

        {/* Order mode is additive, so an existing tip stays visible. */}
        {!isCartMode && currentTip > 0 && (
          <p className="text-sm text-success-600">
            {t("tip.alreadyTipped", {
              amount: `${currencySymbol}${formatAmount(currentTip)}`,
            })}
          </p>
        )}

        <div className="flex gap-2 overflow-x-auto pb-1">
          {presets.map((amount, index) => {
            const isSelected = !isCustomSelected && selectedAmount === amount;
            return (
              <Button
                key={amount}
                variant="bordered"
                isDisabled={busy}
                onPress={() => handlePresetPress(amount)}
                className={`shrink-0 rounded-xl border-1 ${
                  isSelected
                    ? "border-primary bg-primary-50 text-primary font-semibold"
                    : "border-default-200"
                }`}
              >
                <span aria-hidden>{PRESET_EMOJIS[index] || "😀"}</span>
                {currencySymbol}
                {formatAmount(amount)}
              </Button>
            );
          })}
          <Button
            variant="bordered"
            isDisabled={busy}
            onPress={() => {
              setIsCustomOpen(!isCustomOpen);
              if (isCartMode && !isCustomOpen && currentTip > 0) {
                setCustomValue(String(currentTip));
              }
            }}
            className={`shrink-0 rounded-xl border-1 ${
              isCustomSelected
                ? "border-primary bg-primary-50 text-primary font-semibold"
                : "border-default-200"
            }`}
          >
            <span aria-hidden>👏</span>
            {isCustomSelected && !isCustomOpen && selectedAmount > 0
              ? `${currencySymbol}${formatAmount(selectedAmount)}`
              : t("tip.custom")}
          </Button>
        </div>

        {isCustomOpen && (
          <div className="flex gap-2">
            <Input
              type="number"
              min={config.min_tip_amount}
              max={config.max_tip_amount}
              value={customValue}
              onValueChange={setCustomValue}
              isInvalid={!!customError}
              errorMessage={customError}
              classNames={{ errorMessage: "text-sm" }}
              placeholder={t("tip.customPlaceholder", {
                min: `${currencySymbol}${formatAmount(config.min_tip_amount)}`,
                max: `${currencySymbol}${formatAmount(config.max_tip_amount)}`,
              })}
              startContent={
                <span className="text-default-500">{currencySymbol}</span>
              }
              className="flex-1"
            />
            <Button
              color="primary"
              variant="flat"
              isLoading={isSubmitting}
              isDisabled={isDisabled || !customValue || !!customError}
              onPress={handleCustomApply}
            >
              {t("tip.apply")}
            </Button>
          </div>
        )}

        {isCartMode && currentTip > 0 && (
          <div className="flex items-center justify-between gap-2">
            <div>
              <p className="text-sm text-success-600">
                {t("tip.currentAmount", {
                  amount: `${currencySymbol}${formatAmount(currentTip)}`,
                })}
              </p>
            </div>
            {/* The only clear path for a custom tip — presets toggle off by
                re-tap, but 0 is rejected by the custom input's validation. */}
            <Button
              variant="light"
              color="danger"
              isDisabled={busy}
              onPress={() => submit(0)}
            >
              {t("tip.remove")}
            </Button>
          </div>
        )}

        {!isCartMode && (
          <div className="flex flex-col sm:flex-row gap-2">
            <Select
              aria-label={t("tip.paymentMethod")}
              label={t("tip.paymentMethod")}
              selectedKeys={paymentMethod ? [paymentMethod] : []}
              onSelectionChange={(keys) => {
                const key = Array.from(keys)[0];
                if (key) setPaymentMethod(String(key));
              }}
              className="flex-1"
            >
              {paymentMethods.map((method) => (
                <SelectItem key={method}>{t(`tip.methods.${method}`)}</SelectItem>
              ))}
            </Select>
            <Button
              color="primary"
              isLoading={isSubmitting}
              isDisabled={isDisabled || confirmDisabled}
              onPress={() => {
                if (pendingAmount) submit(pendingAmount, paymentMethod);
              }}
              className="sm:self-end"
            >
              {pendingAmount
                ? t("tip.addTipAmount", {
                    amount: `${currencySymbol}${formatAmount(pendingAmount)}`,
                  })
                : t("tip.addTip")}
            </Button>
          </div>
        )}
      </CardBody>
    </Card>
  );
};

export default TipSection;
