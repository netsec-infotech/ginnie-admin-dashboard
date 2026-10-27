import type { FC, ReactNode } from "react";
import { motion } from "framer-motion";
import { LoaderCircle } from "lucide-react";

export interface MediaNavigationOverlayProps {
  label: ReactNode;
}

/** Keeps an immersive viewer stable while its destination route is prepared. */
const MediaNavigationOverlay: FC<MediaNavigationOverlayProps> = ({ label }) => (
  <div
    data-media-navigation
    data-reel-control
    data-story-control
    className="pointer-events-auto absolute inset-0 z-50 flex items-center justify-center"
    role="status"
  >
    <motion.div
      animate={{ opacity: 1, scale: 1 }}
      className="flex items-center gap-2 rounded-full bg-black/65 px-4 py-2.5 text-sm font-semibold text-white shadow-xl backdrop-blur-md"
      initial={{ opacity: 0, scale: 0.96 }}
      transition={{ delay: 0.12, duration: 0.16, ease: "easeOut" }}
    >
      <LoaderCircle
        aria-hidden="true"
        className="animate-spin motion-reduce:animate-none"
        size={17}
      />
      <span>{label}</span>
    </motion.div>
  </div>
);

export default MediaNavigationOverlay;
