import { FC } from "react";
import { Image } from "@heroui/react";

interface ProductIndicatorProps {
  indicator?: "veg" | "non_veg" | null;
  size?: number;
}

const ProductIndicator: FC<ProductIndicatorProps> = ({
  indicator = null,
  size = 16,
}) => {
  if (!indicator) return null;

  if (indicator.toLowerCase() === "veg") {
    return (
      <Image
        src={"/logos/veg-logo.png"}
        alt="Vegetarian"
        width={size}
        height={size}
        radius="none"
      />
    );
  }

  if (indicator.toLowerCase() === "non_veg") {
    return (
      <Image
        src={"/logos/non-veg-logo.png"}
        alt="Non-Vegetarian"
        width={size}
        height={size}
        radius="none"
      />
    );
  }

  return null;
};

export default ProductIndicator;
