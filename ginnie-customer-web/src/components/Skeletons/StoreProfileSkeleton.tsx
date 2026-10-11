import React from "react";
import { Avatar, Skeleton } from "@heroui/react";

const StoreProfileSkeleton: React.FC = () => {
  return (
    <div className="w-full mx-auto rounded-lg overflow-hidden bg-white dark:bg-content1 shadow-lg">
      {/* ================= Banner ================= */}
      <div className="relative h-64 sm:h-72 md:h-80 w-full overflow-hidden">
        <Skeleton className="absolute inset-0 w-full h-full rounded-none" />

        {/* -------- Desktop Overlay -------- */}
        <div className="absolute inset-0 hidden lg:flex flex-col justify-end">
          <div className="relative z-10">
            <div className="relative flex items-end gap-4 px-4 md:px-6 w-full">
              {/* Gradient overlay */}
              {/* <div
                className="absolute 
                  left-0 right-0 
                  -top-10 h-[calc(100%+5rem)]
                  z-0
                  bg-linear-to-t
                  to-transparent 
                  md:from-black/70 
                  md:via-black/60 
                  md:to-transparent"
              /> */}

              {/* Avatar */}
              <div className="-translate-y-1/3 relative z-10">
                <Skeleton className="rounded-full">
                  <Avatar className="w-32 h-32" />
                </Skeleton>
              </div>

              {/* Content */}
              <div className="relative z-10 p-4 sm:p-6 w-full">
                <Skeleton className="h-7 w-64 rounded-lg" />

                <div className="flex flex-wrap items-center gap-2.5 mt-3">
                  <Skeleton className="h-6 w-20 rounded-full" />
                  <Skeleton className="h-6 w-24 rounded-full" />
                  <Skeleton className="h-6 w-16 rounded-full" />
                </div>

                <div className="mt-4 max-w-3xl space-y-2">
                  <Skeleton className="h-4 w-full rounded-md" />
                  <Skeleton className="h-4 w-5/6 rounded-md" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ================= Mobile Content ================= */}
      <div className="block lg:hidden px-4 py-5">
        <div className="relative flex items-end gap-4 w-full">
          <div className="-translate-y-1/3">
            <Skeleton className="rounded-full">
              <Avatar className="w-32 h-32" />
            </Skeleton>
          </div>

          <div className="flex-1 p-4 sm:p-6">
            <Skeleton className="h-7 w-56 rounded-lg" />

            <div className="flex flex-wrap items-center gap-2.5 mt-3">
              <Skeleton className="h-6 w-20 rounded-full" />
              <Skeleton className="h-6 w-24 rounded-full" />
              <Skeleton className="h-6 w-16 rounded-full" />
            </div>

            <div className="mt-4 space-y-2">
              <Skeleton className="h-4 w-full rounded-md" />
              <Skeleton className="h-4 w-4/5 rounded-md" />
            </div>
          </div>
        </div>
      </div>

      {/* ================= Contact Section ================= */}
      <div className="px-4 sm:px-6 py-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="flex items-center gap-3">
              <Skeleton className="w-5 h-5 rounded-full" />
              <Skeleton className="h-4 w-40 rounded-md" />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

export default StoreProfileSkeleton;
