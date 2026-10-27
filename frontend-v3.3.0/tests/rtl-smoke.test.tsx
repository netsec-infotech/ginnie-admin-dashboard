import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";

// A tiny self-contained component to validate the component-testing pipeline:
// jsdom environment + React render + jest-dom matchers + user-event.
function Counter() {
  const [count, setCount] = useState(0);
  return (
    <div>
      <output aria-label="count">{count}</output>
      <button onClick={() => setCount((c) => c + 1)}>Increment</button>
    </div>
  );
}

describe("RTL pipeline smoke test", () => {
  it("renders a component into jsdom", () => {
    render(<Counter />);
    expect(screen.getByLabelText("count")).toHaveTextContent("0");
    expect(
      screen.getByRole("button", { name: "Increment" }),
    ).toBeInTheDocument();
  });

  it("handles user interaction and state updates", async () => {
    const user = userEvent.setup();
    render(<Counter />);
    await user.click(screen.getByRole("button", { name: "Increment" }));
    await user.click(screen.getByRole("button", { name: "Increment" }));
    expect(screen.getByLabelText("count")).toHaveTextContent("2");
  });
});
