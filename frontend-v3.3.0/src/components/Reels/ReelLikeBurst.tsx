import type { FC } from "react";
import { motion, useReducedMotion } from "framer-motion";

export interface ReelLikeBurstState {
  id: number;
  x: number;
  y: number;
}

export interface ReelLikeBurstProps {
  burst: ReelLikeBurstState;
  onComplete: () => void;
}

const ReelLikeBurst: FC<ReelLikeBurstProps> = ({ burst, onComplete }) => {
  const prefersReducedMotion = useReducedMotion();

  return (
    <motion.span
      key={burst.id}
      data-reel-like-burst
      aria-hidden="true"
      className="pointer-events-none absolute z-50 text-white drop-shadow-[0_8px_22px_rgba(0,0,0,0.55)]"
      style={{ left: burst.x, top: burst.y }}
      initial={{
        opacity: 0,
        rotate: -16,
        scale: 0.2,
        x: "-50%",
        y: "-50%",
      }}
      animate={{
        opacity: [0, 1, 1, 0],
        rotate: [-16, 8, 0, 0],
        scale: [0.2, 1.28, 0.94, 1.04],
        y: ["-45%", "-50%", "-52%", "-72%"],
      }}
      exit={{ opacity: 0 }}
      transition={
        prefersReducedMotion
          ? { duration: 0.18 }
          : { duration: 0.78, times: [0, 0.28, 0.58, 1] }
      }
      onAnimationComplete={onComplete}
    >
      <svg
        aria-hidden="true"
        className="h-[92px] w-[92px] overflow-visible"
        viewBox="0 0 24 24"
      >
        <defs>
          <linearGradient
            id={`reel-heart-gradient-${burst.id}`}
            x1="2"
            x2="22"
            y1="22"
            y2="2"
            gradientUnits="userSpaceOnUse"
          >
            <stop offset="0" stopColor="#ffd600" />
            <stop offset="0.28" stopColor="#ff7a00" />
            <stop offset="0.55" stopColor="#ff0169" />
            <stop offset="0.78" stopColor="#d300c5" />
            <stop offset="1" stopColor="#7638fa" />
          </linearGradient>
        </defs>
        <path
          d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
          fill={`url(#reel-heart-gradient-${burst.id})`}
        />
      </svg>
    </motion.span>
  );
};

export default ReelLikeBurst;
