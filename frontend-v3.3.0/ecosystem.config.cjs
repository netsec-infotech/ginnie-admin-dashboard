module.exports = {
  apps: [
    {
      name: "hyperlocal-web",
      cwd: __dirname,
      script: "node_modules/next/dist/bin/next",
      args: "start --hostname 127.0.0.1 --port 3003",
      instances: 1,
      exec_mode: "fork",
      autorestart: true,
      env: {
        NODE_ENV: "production",
        NEXT_PUBLIC_SSR: "true",
      },
    },
  ],
};
