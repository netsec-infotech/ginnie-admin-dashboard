/* eslint @typescript-eslint/no-unused-vars: ["warn", { "varsIgnorePattern": "^_", "argsIgnorePattern": "^_" }] */
import { useState, type ReactNode } from "react";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import translations from "../../public/locales/en.json";
import { supportTopics } from "../fixtures/support";

vi.mock("react-i18next", () => ({
  useTranslation: () => ({
    t: (key: string, args?: Record<string, unknown>) => {
      const value = key
        .split(".")
        .reduce<unknown>(
          (item, part) => (item as Record<string, unknown>)?.[part],
          translations,
        );
      return String(value ?? key).replace(/\{\{(\w+)\}\}/g, (_, name) =>
        String(args?.[name] ?? ""),
      );
    },
    i18n: { language: "en" },
  }),
}));
vi.mock("@/hooks/useSupportOrders", () => ({
  useSupportOrders: () => ({
    orders: [],
    loading: false,
    hasMore: false,
    error: false,
    load: vi.fn(),
  }),
}));
// Keep third-party animation/portal behavior out of flow tests; the browser
// verification uses the real HeroUI controls.
vi.mock("@heroui/react", () => ({
  Button: ({
    children,
    onPress,
    isDisabled,
    isLoading,
    startContent,
    endContent,
    isIconOnly: _icon,
    color: _color,
    variant: _variant,
    radius: _radius,
    size: _size,
    ...props
  }: {
    children?: ReactNode;
    onPress?: () => void;
    isDisabled?: boolean;
    isLoading?: boolean;
    startContent?: ReactNode;
    endContent?: ReactNode;
    [key: string]: unknown;
  }) => (
    <button {...props} onClick={onPress} disabled={isDisabled || isLoading}>
      {startContent}
      {children}
      {endContent}
    </button>
  ),
  Textarea: ({
    onValueChange,
    isDisabled,
    minRows: _min,
    maxRows: _max,
    classNames: _classes,
    ...props
  }: {
    onValueChange: (value: string) => void;
    isDisabled?: boolean;
    [key: string]: unknown;
  }) => (
    <textarea
      {...props}
      disabled={isDisabled}
      onChange={(event) => onValueChange(event.target.value)}
    />
  ),
  Input: ({
    label,
    onValueChange,
    isDisabled,
    isRequired,
    description,
    labelPlacement: _labelPlacement,
    classNames: _classes,
    ...props
  }: {
    label: string;
    onValueChange: (value: string) => void;
    isDisabled?: boolean;
    isRequired?: boolean;
    description?: string;
    [key: string]: unknown;
  }) => (
    <label>
      {label}
      <input
        {...props}
        disabled={isDisabled}
        required={isRequired}
        onChange={(event) => onValueChange(event.target.value)}
      />
      {description}
    </label>
  ),
  Modal: ({ children, isOpen }: { children: ReactNode; isOpen?: boolean }) =>
    isOpen ? <div role="dialog">{children}</div> : null,
  ModalContent: ({ children }: { children: ReactNode }) => (
    <div>{children}</div>
  ),
  ModalHeader: ({ children }: { children: ReactNode }) => <h2>{children}</h2>,
  ModalBody: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  ModalFooter: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));
import SupportComposer from "@/components/Support/SupportComposer";
import SupportStartFlow from "@/components/Support/SupportStartFlow";
import {
  SupportCallbackDialog,
  SupportRating,
} from "@/components/Support/SupportSessionActions";

describe("support customer flow", () => {
  it("requires text or a file, sends Enter, and preserves Shift+Enter/IME composition", () => {
    const send = vi.fn();
    function Composer() {
      const [message, setMessage] = useState("");
      return (
        <SupportComposer
          message={message}
          files={[]}
          onMessage={setMessage}
          onFiles={vi.fn()}
          onSubmit={send}
        />
      );
    }
    render(<Composer />);
    expect(screen.getByRole("button", { name: "Send" })).toBeDisabled();
    fireEvent.change(screen.getByRole("textbox"), {
      target: { value: "Hello" },
    });
    fireEvent.keyDown(screen.getByRole("textbox"), {
      key: "Enter",
      shiftKey: true,
    });
    fireEvent.keyDown(screen.getByRole("textbox"), {
      key: "Enter",
      isComposing: true,
    });
    expect(send).not.toHaveBeenCalled();
    fireEvent.keyDown(screen.getByRole("textbox"), { key: "Enter" });
    expect(send).toHaveBeenCalledTimes(1);
  });
  it("keeps the composer focused and editable while a message is sending", async () => {
    function Composer() {
      const [message, setMessage] = useState("Hello");
      return (
        <SupportComposer
          message={message}
          files={[]}
          onMessage={setMessage}
          onFiles={vi.fn()}
          onSubmit={() => {
            setMessage("");
          }}
        />
      );
    }
    render(<Composer />);
    const message = screen.getByRole("textbox");
    screen.getByRole("button", { name: "Send" }).focus();
    fireEvent.click(screen.getByRole("button", { name: "Send" }));
    await waitFor(() => expect(message).toHaveFocus());
    expect(message).toBeEnabled();
    fireEvent.change(message, { target: { value: "Next message" } });
    expect(message).toHaveValue("Next message");
    expect(screen.getByRole("button", { name: "Send" })).toBeEnabled();
  });
  it("rejects an excessive drop without changing selected attachments", () => {
    const files = vi.fn();
    render(
      <SupportComposer
        message=""
        files={[]}
        onMessage={vi.fn()}
        onFiles={files}
        onSubmit={vi.fn()}
      />,
    );
    fireEvent.drop(screen.getByRole("form"), {
      dataTransfer: {
        files: Array.from({ length: 6 }, () => new File([], "test")),
      },
    });
    expect(screen.getByRole("alert")).toHaveTextContent("up to 5 files");
    expect(files).not.toHaveBeenCalled();
  });
  it("rejects unsupported files from the picker and explains accepted formats", () => {
    const files = vi.fn();
    render(
      <SupportComposer
        message=""
        files={[]}
        onMessage={vi.fn()}
        onFiles={files}
        onSubmit={vi.fn()}
        start
      />,
    );

    const picker = document.querySelector(
      'input[type="file"]',
    ) as HTMLInputElement;
    expect(picker).toHaveAttribute("accept", ".jpg,.jpeg,.png,.webp,.pdf");
    fireEvent.change(picker, {
      target: { files: [new File([], "script.exe")] },
    });
    expect(screen.getByRole("alert")).toHaveTextContent("Only JPG, PNG, WebP");
    expect(files).not.toHaveBeenCalled();
    expect(screen.getByText(/Up to 5 files, 10 MB each/)).toBeInTheDocument();
  });
  it("previews selected images and PDFs, then releases previews on removal", () => {
    const createObjectURL = vi.fn((file: File) => `blob:${file.name}`);
    const revokeObjectURL = vi.fn();
    vi.stubGlobal(
      "URL",
      class extends URL {
        static createObjectURL = createObjectURL;
        static revokeObjectURL = revokeObjectURL;
      },
    );

    const image = new File(["image"], "damage.jpg", { type: "image/jpeg" });
    const pdf = new File(["pdf"], "receipt.pdf", { type: "application/pdf" });
    function Composer() {
      const [files, setFiles] = useState([image, pdf]);
      return (
        <SupportComposer
          message=""
          files={files}
          onMessage={vi.fn()}
          onFiles={setFiles}
          onSubmit={vi.fn()}
          start
        />
      );
    }

    const { unmount } = render(<Composer />);
    expect(screen.getByRole("img", { name: "damage.jpg" })).toHaveAttribute(
      "src",
      "blob:damage.jpg",
    );
    expect(
      screen.getByRole("link", { name: "Preview receipt.pdf" }),
    ).toHaveAttribute("href", "blob:receipt.pdf");
    fireEvent.click(screen.getByRole("button", { name: "Preview damage.jpg" }));
    expect(screen.getByRole("dialog")).toContainElement(
      screen.getAllByRole("img", { name: "damage.jpg" })[1],
    );
    fireEvent.click(screen.getByRole("button", { name: "Remove damage.jpg" }));
    expect(revokeObjectURL).toHaveBeenCalledWith("blob:damage.jpg");
    unmount();
    expect(revokeObjectURL).toHaveBeenCalledWith("blob:receipt.pdf");
    vi.unstubAllGlobals();
  });
  it("keeps a selected order through topic selection and chat creation", () => {
    const start = vi.fn();
    render(
      <SupportStartFlow
        orders={[
          {
            id: 1400,
            slug: "order-1400",
            number: "#1400",
            status: "delivered",
            currency_code: "INR",
            total: "30.00",
            created_at: "2026-07-29T00:00:00Z",
          },
          {
            id: 1334,
            slug: "order-1334",
            number: "#1334",
            store_names: ["Neighborhood Market"],
            items: [{ title: "Milk", quantity: 2, variant_title: "1 litre" }],
            payment_method: "cash_on_delivery",
            payment_status: "unpaid",
            status: "cancelled",
            currency_code: "INR",
            total: "25.22",
            created_at: "2026-07-30T00:00:00Z",
          },
        ]}
        topics={[
          {
            id: 1,
            slug: "delivery",
            title: "Delivery or tracking",
            context: "order",
            guidance: null,
            quick_replies: [],
          },
          {
            id: 2,
            slug: "damaged",
            title: "Damaged or wrong item",
            context: "order",
            guidance: null,
            quick_replies: [],
          },
          {
            id: 3,
            slug: "return",
            title: "Return or refund",
            context: "order",
            guidance: null,
            quick_replies: [],
          },
          {
            id: 4,
            slug: "payment",
            title: "Payment issue",
            context: "both",
            guidance: null,
            quick_replies: [],
          },
          {
            id: 5,
            slug: "general",
            title: "Account or other help",
            context: "general",
            guidance: null,
            quick_replies: [],
          },
        ]}
        composer={{
          message: "Please help",
          files: [],
          onMessage: vi.fn(),
          onFiles: vi.fn(),
        }}
        onStart={start}
        onRetry={vi.fn()}
      />,
    );
    expect(
      screen.getAllByRole("button", { name: /#\d+/ })[0],
    ).toHaveTextContent("#1334");
    const orderCard = screen.getByRole("button", { name: /#1334/ });
    expect(orderCard).toHaveTextContent("Neighborhood Market");
    expect(orderCard).toHaveTextContent("2 × Milk (1 litre)");
    expect(orderCard).toHaveTextContent("Payment Method: cash on delivery");
    expect(orderCard).toHaveTextContent("Payment Status: unpaid");
    fireEvent.click(orderCard);
    for (const name of [
      "Delivery or tracking",
      "Damaged or wrong item",
      "Return or refund",
      "Payment issue",
    ]) {
      expect(screen.getByRole("button", { name })).toBeInTheDocument();
    }
    expect(
      screen.queryByRole("button", { name: "Account or other help" }),
    ).not.toBeInTheDocument();
    fireEvent.click(
      screen.getByRole("button", { name: "Delivery or tracking" }),
    );
    fireEvent.click(screen.getByRole("button", { name: "Start chat" }));
    expect(start).toHaveBeenCalledWith(1, 1334);
    fireEvent.click(screen.getAllByRole("button", { name: "Back" })[0]);
    fireEvent.click(screen.getByRole("button", { name: "Back" }));
    fireEvent.click(
      screen.getByRole("button", {
        name: "My issue is not related to an order",
      }),
    );
    expect(
      screen.queryByRole("button", { name: "Delivery or tracking" }),
    ).not.toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Payment issue" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Account or other help" }),
    ).toBeInTheDocument();
  });
  it("filters general topics, fills quick replies, and preserves the draft on Back", () => {
    const start = vi.fn();
    function Flow() {
      const [message, setMessage] = useState("");
      return (
        <SupportStartFlow
          orders={[]}
          topics={supportTopics}
          composer={{
            message,
            files: [],
            onMessage: setMessage,
            onFiles: vi.fn(),
          }}
          onStart={start}
          onRetry={vi.fn()}
        />
      );
    }
    render(<Flow />);
    fireEvent.click(
      screen.getByRole("button", {
        name: "My issue is not related to an order",
      }),
    );
    expect(
      screen.queryByRole("button", { name: /Delivery/ }),
    ).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Account" }));
    fireEvent.click(screen.getByRole("button", { name: "Account question" }));
    expect(screen.getByRole("textbox")).toHaveValue("Account question");
    fireEvent.click(screen.getAllByRole("button", { name: "Back" })[0]);
    fireEvent.click(screen.getByRole("button", { name: "Account" }));
    expect(screen.getByRole("textbox")).toHaveValue("Account question");
    fireEvent.click(screen.getByRole("button", { name: "Start chat" }));
    expect(start).toHaveBeenCalledWith(2, undefined);
  });
  it("validates callback scheduling and keeps errors visible inside the dialog", async () => {
    const submit = vi.fn().mockResolvedValue(false);
    render(
      <SupportCallbackDialog
        phone="+123"
        busy={false}
        onClose={vi.fn()}
        onSubmit={submit}
      />,
    );
    fireEvent.change(screen.getByLabelText(/Preferred callback/), {
      target: { value: "2020-01-01T10:00" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Request callback" }));
    expect(submit).not.toHaveBeenCalled();
    expect(screen.getByRole("alert")).toHaveTextContent(
      "Choose a future callback time.",
    );
    expect(screen.getByRole("alert")).not.toHaveTextContent("phone number");
    fireEvent.change(screen.getByLabelText(/Preferred callback/), {
      target: { value: "" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Request callback" }));
    await waitFor(() =>
      expect(screen.getByRole("alert")).toHaveTextContent("Unable to complete"),
    );
  });
  it("requires a star selection and submits the chosen score with feedback", () => {
    const submit = vi.fn().mockResolvedValue(true);
    render(<SupportRating busy={false} onSubmit={submit} />);
    expect(
      screen.getByRole("button", { name: "Submit rating" }),
    ).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "4 stars" }));
    fireEvent.change(screen.getByRole("textbox"), {
      target: { value: "Helpful" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Submit rating" }));
    expect(submit).toHaveBeenCalledWith(4, "Helpful");
  });
});
