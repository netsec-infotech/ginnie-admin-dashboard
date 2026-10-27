import type { FC } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Heart } from "lucide-react";

export interface StoryLikeBurstProps {
  burstKey: number;
}

const StoryLikeBurst: FC<StoryLikeBurstProps> = ({ burstKey }) => {
  const prefersReducedMotion = useReducedMotion();

  return (
    <motion.div
      key={burstKey}
      aria-hidden="true"
      data-story-like-burst
      className="pointer-events-none absolute inset-0 z-40 flex items-center justify-center"
      initial={{ opacity: 0, scale: 0.35, rotate: -12 }}
      animate={{
        opacity: [0, 1, 1],
        scale: [0.35, 1.18, 1],
        rotate: [-12, 6, 0],
      }}
      exit={{ opacity: 0, scale: 1.35 }}
      transition={
        prefersReducedMotion
          ? { duration: 0 }
          : { duration: 0.48, ease: [0.22, 1, 0.36, 1] }
      }
    >
      <Heart
        className="fill-rose-500 text-rose-500 drop-shadow-[0_12px_30px_rgba(0,0,0,0.45)]"
        size={96}
        strokeWidth={1.7}
      />
    </motion.div>
  );
};

export default StoryLikeBurst;
