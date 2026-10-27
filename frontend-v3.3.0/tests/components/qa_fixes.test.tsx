import type { ReactNode } from "react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import type { Order } from "@/types/ApiResponse";
import enTranslations from "@/../public/locales/en.json";
import hiTranslations from "@/../public/locales/hi.json";
import arTranslations from "@/../public/locales/ar.json";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const flatMap: Record<string, string> = {
        finalTotal: "Final Total",
        payable_on_delivery: "Payable on Delivery",
        totalPayable: "Total Payable",
        date: "Date",
        "labels.return_proof_images_required":
          "Please upload proof images to return this item.",
        return_proof_images_required:
          "Please upload proof images to return this item.",
        return_proof_required:
          "Please add at least one photo as proof.",
      };
      return flatMap[key] || key;
    },
  }),
}));

vi.mock("@/contexts/SettingsContext", () => ({
  useSettings: () => ({ currencySymbol: "$" }),
}));

vi.mock("@/helpers/getters", () => ({
  getFormattedDate: () => "01-08-2026",
  getOrderStatusBtnConfig: () => ({}),
  isStatusBeforeOrAt: () => true,
}));

vi.mock("@/routes/api", () => ({
  reorderOrder: vi.fn(),
  getOrders: vi.fn(),
}));

vi.mock("@/helpers/updators", () => ({ updateCartData: vi.fn() }));
vi.mock("@/components/Modals/CancelOrderItemModal", () => ({ default: () => null }));
vi.mock("@/components/Modals/ReturnOrderItemModal", () => ({ default: () => null }));
vi.mock("@/components/Modals/RatingModal", () => ({ default: () => null }));
vi.mock("@/components/Modals/OrderItemReviewCard", () => ({ default: () => null }));
vi.mock("@/components/FilePreview", () => ({ default: () => null }));
vi.mock("next/dynamic", () => ({ default: () => () => null }));
vi.mock("yet-another-react-lightbox", () => ({ default: () => null }));

vi.mock("@heroui/react", () => {
  const Box = ({ children, ...props }: { children?: ReactNode }) => (
    <div {...props}>{children}</div>
  );
  return {
    Chip: Box,
    Card: Box,
    CardBody: Box,
    CardHeader: Box,
    CardFooter: Box,
    Accordion: Box,
    AccordionItem: Box,
    ScrollShadow: Box,
    Button: Box,
    Divider: () => null,
    Image: () => null,
    addToast: vi.fn(),
    useDisclosure: () => ({ isOpen: false, onOpen: vi.fn(), onClose: vi.fn() }),
  };
});

import OrderCard from "@/components/Cards/OrderCard";

describe("QA Fixes Verification", () => {
  describe("Issue 15: Locale files include return proof required keys", () => {
    it("should have return_proof_images_required in en.json", () => {
      expect((enTranslations as any)["labels.return_proof_images_required"]).toBe(
        "Please upload proof images to return this item."
      );
      expect((enTranslations as any).labels?.return_proof_images_required).toBe(
        "Please upload proof images to return this item."
      );
    });

    it("should have return_proof_images_required in hi.json", () => {
      expect((hiTranslations as any)["labels.return_proof_images_required"]).toBe(
        "कृपया इस आइटम को वापस करने के लिए प्रमाण चित्र अपलोड करें।"
      );
    });

    it("should have return_proof_images_required in ar.json", () => {
      expect((arTranslations as any)["labels.return_proof_images_required"]).toBe(
        "يرجى تحميل صور الإثبات لإرجاع هذا العنصر."
      );
    });
  });

  describe("Issue 4.3: OrderCard payable amount display", () => {
    it("renders both Final Total and Payable on Delivery for COD when amounts differ", () => {
      const mockOrder = {
        id: 101,
        uuid: "ord-101",
        created_at: "2026-08-01T10:00:00Z",
        final_total: "150.00",
        total_payable: "90.00",
        payment_method: "cod",
        status: "confirmed",
        items: [],
      } as unknown as Order;

      render(<OrderCard order={mockOrder} />);

      expect(screen.getByText("Final Total")).toBeDefined();
      expect(screen.getByText("$150.00")).toBeDefined();
      expect(screen.getByText("Payable on Delivery: $90.00")).toBeDefined();
    });

    it("renders Total Payable for non-COD payment when amounts differ", () => {
      const mockOrder = {
        id: 102,
        uuid: "ord-102",
        created_at: "2026-08-01T10:00:00Z",
        final_total: "200.00",
        total_payable: "120.00",
        payment_method: "stripe",
        status: "confirmed",
        items: [],
      } as unknown as Order;

      render(<OrderCard order={mockOrder} />);

      expect(screen.getByText("Total Payable: $120.00")).toBeDefined();
    });

    it("does not render extra payable line when total_payable matches final_total", () => {
      const mockOrder = {
        id: 103,
        uuid: "ord-103",
        created_at: "2026-08-01T10:00:00Z",
        final_total: "100.00",
        total_payable: "100.00",
        payment_method: "cod",
        status: "confirmed",
        items: [],
      } as unknown as Order;

      render(<OrderCard order={mockOrder} />);

      expect(screen.queryByText(/Payable on Delivery/)).toBeNull();
      expect(screen.queryByText(/Total Payable:/)).toBeNull();
    });
  });

  describe("Issue 16: Delivery Type filter options in locales", () => {
    it("has deliveryType and deliveryTypeFilters in en.json", () => {
      expect((enTranslations as any).filters?.deliveryType).toBe("Delivery Type");
      expect((enTranslations as any).deliveryTypeFilters?.delivery).toBe("Home Delivery");
      expect((enTranslations as any).deliveryTypeFilters?.pickup).toBe("Self Pickup");
      expect((enTranslations as any).deliveryTypeFilters?.scheduled).toBe("Scheduled Delivery");
      expect((enTranslations as any).deliveryTypeFilters?.pos).toBe("POS / In-Store");
    });

    it("has deliveryType and deliveryTypeFilters in hi.json", () => {
      expect((hiTranslations as any).filters?.deliveryType).toBe("डिलीवरी का प्रकार");
      expect((hiTranslations as any).deliveryTypeFilters?.delivery).toBe("होम डिलीवरी");
      expect((hiTranslations as any).deliveryTypeFilters?.pickup).toBe("सेल्फ पिकअप");
      expect((hiTranslations as any).deliveryTypeFilters?.scheduled).toBe("शेड्यूल डिलीवरी");
      expect((hiTranslations as any).deliveryTypeFilters?.pos).toBe("पीओएस / इन-स्टोर");
    });

    it("has deliveryType and deliveryTypeFilters in ar.json", () => {
      expect((arTranslations as any).filters?.deliveryType).toBe("نوع التوصيل");
      expect((arTranslations as any).deliveryTypeFilters?.delivery).toBe("توصيل للمنزل");
      expect((arTranslations as any).deliveryTypeFilters?.pickup).toBe("استلام ذاتي");
      expect((arTranslations as any).deliveryTypeFilters?.scheduled).toBe("توصيل مجدول");
      expect((arTranslations as any).deliveryTypeFilters?.pos).toBe("نقاط البيع / بالمتجر");
    });
  });
});
